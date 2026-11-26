<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\CatalogProductEvent;
use App\Dto\SearchFilters;
use App\Entity\ProductProjection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use JsonException;
use Pgvector\Vector;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * @extends ServiceEntityRepository<ProductProjection>
 */
class ProductProjectionRepository extends ServiceEntityRepository
{
    private const float CATEGORY_BONUS = 0.15;

    private const float ATTRIBUTE_BONUS = 0.05;

    private const int CANDIDATE_WINDOW = 25;

    /** @var list<string> */
    private const array ITERATIVE_SCAN_MODES = ['relaxed_order', 'strict_order'];

    private bool $iterativeScanResolved = false;

    private ?string $iterativeScan = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductProjection::class);
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    /**
     * @throws JsonException
     */
    public function upsertProjection(CatalogProductEvent $product): void
    {
        $vectorString = null === $product->embedding ? null : (string) new Vector($product->embedding);
        $attributesJson = json_encode($product->attributes, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        $sql = <<<'SQL'
                INSERT INTO product_projections (
                    product_id,
                    store_id,
                    name,
                    description,
                    category_name,
                    price,
                    is_published,
                    is_in_stock,
                    product_url,
                    attributes,
                    embedding,
                    updated_at
                ) VALUES (
                    :product_id,
                    :store_id,
                    :name,
                    :description,
                    :category_name,
                    :price,
                    :is_published,
                    :is_in_stock,
                    :product_url,
                    CAST(:attributes AS json),
                    CAST(:embedding AS vector),
                    NOW()
                )
                ON CONFLICT (product_id) DO UPDATE SET
                    store_id = EXCLUDED.store_id,
                    name = EXCLUDED.name,
                    description = EXCLUDED.description,
                    category_name = EXCLUDED.category_name,
                    price = EXCLUDED.price,
                    is_published = EXCLUDED.is_published,
                    is_in_stock = EXCLUDED.is_in_stock,
                    product_url = EXCLUDED.product_url,
                    attributes = EXCLUDED.attributes,
                    embedding = EXCLUDED.embedding,
                    updated_at = NOW()
            SQL;

        $this->getConnection()->executeStatement($sql, [
            'product_id' => $product->product_id,
            'store_id' => $product->store_id,
            'name' => $product->name,
            'description' => $product->description,
            'category_name' => $product->category,
            'price' => $product->price,
            'is_published' => $product->is_published,
            'is_in_stock' => $product->is_in_stock,
            'product_url' => $product->product_url,
            'attributes' => $attributesJson,
            'embedding' => $vectorString,
        ]);
    }

    /**
     * @return list<string>
     */
    public function distinctCategoryNames(int $storeId): array
    {
        /** @var list<mixed> $names */
        $names = $this->createQueryBuilder('p')
            ->select('DISTINCT p.categoryName')
            ->andWhere('p.storeId = :storeId')
            ->andWhere('p.categoryName <> :empty')
            ->setParameter('storeId', $storeId)
            ->setParameter('empty', '')
            ->orderBy('p.categoryName', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $categories = [];
        foreach ($names as $name) {
            if (!\is_string($name)) {
                continue;
            }
            $trimmed = trim($name);
            if ('' !== $trimmed) {
                $categories[] = $trimmed;
            }
        }

        return $categories;
    }

    /**
     * Поиск похожих товаров. Жёсткий WHERE применяется только к числовому price_max
     * (и активным товарам магазина в наличии). Категория и атрибуты дают бонус к score;
     * если выборка пустая — выполняется fallback-повтор без мягких ограничений.
     *
     * @param list<float> $embedding
     *
     * @return list<array<string, mixed>>
     */
    public function searchSimilar(
        array $embedding,
        int $storeId,
        SearchFilters $filters,
        int $limit = 5,
        float $threshold = 0.57,
    ): array {
        return $this->withIterativeScan(function () use ($embedding, $storeId, $filters, $limit, $threshold): array {
            $hasSoft = $filters->hasSoftConstraints();
            $windowLimit = $hasSoft ? max($limit, self::CANDIDATE_WINDOW) : $limit;
            $candidates = $this->executeVectorQuery($embedding, $storeId, $filters, $windowLimit, $threshold);

            if ([] === $candidates && $hasSoft) {
                $fallback = $this->executeVectorQuery(
                    $embedding,
                    $storeId,
                    $filters->withoutSoftConstraints(),
                    $limit,
                    $threshold,
                );

                return $this->present($fallback);
            }

            if (!$hasSoft) {
                return $this->present($candidates);
            }

            return $this->present($this->rerank($candidates, $filters, $limit));
        });
    }

    public function updateAvailability(int $productId, bool $inStock): void
    {
        $this->getConnection()->executeStatement(
            'UPDATE product_projections SET is_in_stock = :is_in_stock, updated_at = NOW() WHERE product_id = :product_id',
            [
                'product_id' => $productId,
                'is_in_stock' => $inStock,
            ],
        );
    }

    public function deleteByProductId(int $productId): void
    {
        $this->getConnection()->executeStatement(
            'DELETE FROM product_projections WHERE product_id = :product_id',
            ['product_id' => $productId],
        );
    }

    /**
     * @param list<float> $embedding
     *
     * @return list<array<string, mixed>>
     */
    private function executeVectorQuery(
        array $embedding,
        int $storeId,
        SearchFilters $filters,
        int $limit,
        float $threshold,
    ): array {
        $query = $this->createQueryBuilder('p')
            ->addSelect('(1 - cosine_distance(p.embedding, :embedding)) AS similarity')
            ->addSelect('cosine_distance(p.embedding, :embedding) AS HIDDEN distance')
            ->andWhere('p.storeId = :storeId')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isInStock = true')
            ->andWhere('p.embedding IS NOT NULL')
            ->andWhere('cosine_distance(p.embedding, :embedding) <= :maxDistance')
            ->setParameter('embedding', new Vector($embedding), 'vector')
            ->setParameter('storeId', $storeId)
            ->setParameter('maxDistance', 1 - $threshold)
            ->orderBy('distance', 'ASC')
            ->setMaxResults($limit);

        $price = $filters->priceMax();
        if (null !== $price && is_finite($price)) {
            $query->andWhere('p.price <= :priceMax')
                ->setParameter('priceMax', \sprintf('%.2F', $price));
        }

        $rows = [];
        foreach ($query->getQuery()->getResult() as $row) {
            $rows[] = $this->mapResultRow($row);
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function rerank(array $rows, SearchFilters $filters, int $limit): array
    {
        foreach ($rows as &$row) {
            $row['_score'] = (float) $row['similarity'] + $this->bonus($row, $filters);
        }
        unset($row);

        usort($rows, static fn (array $left, array $right): int => $right['_score'] <=> $left['_score']
            ?: ((float) $right['similarity']) <=> ((float) $left['similarity'])
            ?: $left['product_id'] <=> $right['product_id']);

        $rows = \array_slice($rows, 0, $limit);
        foreach ($rows as &$row) {
            unset($row['_score']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function bonus(array $row, SearchFilters $filters): float
    {
        $bonus = 0.0;
        $category = $filters->categoryOrNull();
        $rowCategory = $row['category'] ?? null;
        if (null !== $category && \is_string($rowCategory) && $this->sameText($rowCategory, $category)) {
            $bonus += self::CATEGORY_BONUS;
        }

        $attributes = $row['attributes'] ?? [];
        if ($attributes instanceof stdClass) {
            $attributes = (array) $attributes;
        }
        if (!\is_array($attributes)) {
            return $bonus;
        }

        foreach ($filters->comparableAttributes() as $key => $expected) {
            $actual = $attributes[$key] ?? null;
            if (\is_scalar($actual) && $this->sameText(trim((string) $actual), $expected)) {
                $bonus += self::ATTRIBUTE_BONUS;
            }
        }

        return $bonus;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function present(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['similarity'] = number_format((float) $row['similarity'], 4, '.', '');
            $row['attributes'] = $this->normalizeAttributes($row['attributes'] ?? []);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapResultRow(mixed $row): array
    {
        if (!\is_array($row) || !isset($row[0]) || !$row[0] instanceof ProductProjection) {
            throw new UnexpectedValueException('Векторный поиск вернул неожиданную строку.');
        }

        $product = $row[0];

        return [
            'product_id' => $product->getProductId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'category' => $product->getCategoryName(),
            'price' => $product->getPrice(),
            'attributes' => $this->normalizeAttributes($product->getAttributes()),
            'product_url' => $product->getProductUrl(),
            'similarity' => (float) ($row['similarity'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAttributes(mixed $attributes): array
    {
        if ($attributes instanceof stdClass) {
            return (array) $attributes;
        }

        if (\is_string($attributes)) {
            try {
                $decoded = json_decode($attributes, true, 512, \JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
            $attributes = $decoded;
        }

        return \is_array($attributes) ? $attributes : [];
    }

    private function sameText(string $left, string $right): bool
    {
        return mb_strtolower($left) === mb_strtolower($right);
    }

    /**
     * @template T
     *
     * @param callable(): T $query
     *
     * @return T
     */
    private function withIterativeScan(callable $query): mixed
    {
        $connection = $this->getConnection();
        $mode = $this->resolveIterativeScan($connection);
        $connection->beginTransaction();
        try {
            if (null !== $mode) {
                $connection->executeStatement(\sprintf("SET LOCAL hnsw.iterative_scan = '%s'", $mode));
            }
            $result = $query();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    private function resolveIterativeScan(Connection $connection): ?string
    {
        if ($this->iterativeScanResolved) {
            return $this->iterativeScan;
        }

        $this->iterativeScanResolved = true;
        foreach (self::ITERATIVE_SCAN_MODES as $mode) {
            if ($this->iterativeScanAccepted($connection, $mode)) {
                $this->iterativeScan = $mode;

                return $mode;
            }
        }

        return null;
    }

    private function iterativeScanAccepted(Connection $connection, string $mode): bool
    {
        if (!\in_array($mode, self::ITERATIVE_SCAN_MODES, true)) {
            return false;
        }

        $connection->beginTransaction();
        try {
            $connection->executeStatement(\sprintf("SET LOCAL hnsw.iterative_scan = '%s'", $mode));
            $connection->commit();

            return true;
        } catch (Throwable) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            return false;
        }
    }
}
