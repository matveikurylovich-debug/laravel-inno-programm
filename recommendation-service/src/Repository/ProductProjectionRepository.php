<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductProjection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use JsonException;

/**
 * @extends ServiceEntityRepository<ProductProjection>
 */
class ProductProjectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductProjection::class);
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    /**
     * Идемпотентная запись/обновление проекции товара.
     *
     * @param array{
     *     product_id: int,
     *     store_id: int,
     *     name: string,
     *     description?: ?string,
     *     category?: string,
     *     category_name?: string,
     *     price: float|int|string,
     *     is_published?: bool,
     *     is_in_stock?: bool,
     *     product_url?: string,
     *     attributes?: array<string, mixed>,
     *     embedding?: array<float>|null
     * } $data
     *
     * @throws JsonException
     */
    public function upsertProjection(array $data): void
    {
        $conn = $this->getConnection();

        $vectorString = null;
        if (!empty($data['embedding'])) {
            $vectorString = '['.implode(',', $data['embedding']).']';
        }

        $attributesJson = json_encode(
            $data['attributes'] ?? [],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );

        $categoryName = (string) ($data['category_name'] ?? $data['category'] ?? '');
        $productUrl = (string) ($data['product_url'] ?? sprintf('/products/%d', (int) $data['product_id']));

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

        $conn->executeStatement($sql, [
            'product_id' => $data['product_id'],
            'store_id' => $data['store_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_name' => $categoryName,
            'price' => (float) $data['price'],
            'is_published' => (bool) ($data['is_published'] ?? true),
            'is_in_stock' => (bool) ($data['is_in_stock'] ?? true),
            'product_url' => $productUrl,
            'attributes' => $attributesJson,
            'embedding' => $vectorString,
        ]);
    }

    /**
     * Поиск похожих товаров по косинусному сходству с применением фильтров.
     *
     * @param array<float>              $embedding Вектор запроса (размерность 768)
     * @param array<string, mixed>|null $filters   Фильтры, извлеченные из запроса
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchSimilar(
        array $embedding,
        int $storeId,
        ?array $filters = null,
        int $limit = 5,
        float $threshold = 0.57,
    ): array {
        $conn = $this->getConnection();

        $vectorString = '['.implode(',', $embedding).']';

        $whereConditions = [
            'p.store_id = :store_id',
            'p.is_published = true',
            'p.is_in_stock = true',
            'p.embedding IS NOT NULL',
            '(1 - (p.embedding <=> CAST(:vector AS vector))) >= :threshold',
        ];

        $params = [
            'store_id' => $storeId,
            'vector' => $vectorString,
            'threshold' => $threshold,
            'limit' => $limit,
        ];

        if (!empty($filters['category'])) {
            $whereConditions[] = 'LOWER(p.category_name) = LOWER(:category)';
            $params['category'] = (string) $filters['category'];
        }

        if (!empty($filters['price_max']) && is_numeric($filters['price_max'])) {
            $whereConditions[] = 'p.price <= :price_max';
            $params['price_max'] = (float) $filters['price_max'];
        }

        if (!empty($filters['attributes']['color'])) {
            $whereConditions[] = "LOWER(p.attributes->>'color') = LOWER(:color)";
            $params['color'] = (string) $filters['attributes']['color'];
        }

        if (!empty($filters['attributes']['material'])) {
            $whereConditions[] = "LOWER(p.attributes->>'material') = LOWER(:material)";
            $params['material'] = (string) $filters['attributes']['material'];
        }

        $sql = sprintf(
            'SELECT 
                p.product_id,
                p.name,
                p.description,
                p.category_name AS category,
                p.price,
                p.attributes,
                p.product_url,
                ROUND((1 - (p.embedding <=> CAST(:vector AS vector)))::numeric, 4) AS similarity
             FROM product_projections p
             WHERE %s
             ORDER BY p.embedding <=> CAST(:vector AS vector) ASC
             LIMIT :limit',
            implode(' AND ', $whereConditions)
        );

        $stmt = $conn->executeQuery($sql, $params);

        return $stmt->fetchAllAssociative();
    }

    /**
     * Удаление проекции товара по ID (при удалении из каталога).
     */
    public function deleteByProductId(int $productId): void
    {
        $this->getConnection()->executeStatement(
            'DELETE FROM product_projections WHERE product_id = :product_id',
            ['product_id' => $productId]
        );
    }
}
