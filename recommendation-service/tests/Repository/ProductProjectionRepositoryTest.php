<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Dto\SearchFilters;
use App\Entity\ProductProjection;
use App\EventListener\HnswIndexSchemaListener;
use App\Repository\ProductProjectionRepository;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use Pgvector\Vector;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductProjectionRepositoryTest extends KernelTestCase
{
    public function testPgvectorCosineDistanceDqlAndHnswSchemaListener(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->getConnection();

        $dql = \sprintf(
            'SELECT p, (1 - cosine_distance(p.embedding, :embedding)) AS similarity, cosine_distance(p.embedding, :embedding) AS HIDDEN distance FROM %s p WHERE p.storeId = :storeId AND p.isPublished = true AND p.isInStock = true AND p.price <= :priceMax AND cosine_distance(p.embedding, :embedding) <= :maxDistance ORDER BY distance ASC',
            ProductProjection::class,
        );
        $sql = $em->createQuery($dql)->getSQL();
        $this->assertIsString($sql);
        $this->assertStringContainsString('<=>', $sql);
        $this->assertStringNotContainsString('category_name =', $sql);

        $metadata = $em->getClassMetadata(ProductProjection::class);
        $fieldMapping = $metadata->getFieldMapping('embedding');
        $this->assertSame('vector', $fieldMapping->type);
        $this->assertNull($fieldMapping->length);

        $schema = new Schema()->edit()
            ->addTable(
                Table::editor()
                    ->setUnquotedName('product_projections')
                    ->setColumns(
                        Column::editor()
                            ->setUnquotedName('embedding')
                            ->setTypeName('vector')
                            ->setNotNull(false)
                            ->create(),
                    )
                    ->create(),
            )
            ->create();

        $event = new GenerateSchemaEventArgs($em, $schema);
        new HnswIndexSchemaListener()->postGenerateSchema($event);

        $updatedTable = $event->getSchema()->getTable('product_projections');
        $this->assertTrue($updatedTable->hasIndex('idx_products_embedding_hnsw'));
        $this->assertSame(['embedding'], $updatedTable->getIndex('idx_products_embedding_hnsw')->getColumns());
        $this->assertSame('vector', $updatedTable->getColumn('embedding')->getType()->getSQLDeclaration(
            $updatedTable->getColumn('embedding')->toArray(),
            new PostgreSQLPlatform(),
        ));
    }

    public function testRerankAppliesCategoryAndAttributeScoreBonusesWithoutDroppingOtherCategories(): void
    {
        self::bootKernel();
        /** @var ProductProjectionRepository $repository */
        $repository = self::getContainer()->get(ProductProjectionRepository::class);

        $candidates = [
            [
                'product_id' => 10,
                'name' => 'Куртка треккинговая',
                'category' => 'Куртки',
                'price' => '9500.00',
                'attributes' => ['color' => 'синий'],
                'similarity' => 0.86,
            ],
            [
                'product_id' => 11,
                'name' => 'Кроссовки беговые',
                'category' => 'Обувь',
                'price' => '8900.00',
                'attributes' => ['color' => 'черный', 'material' => 'мембрана'],
                'similarity' => 0.75,
            ],
        ];

        $reranked = $repository->rerank(
            $candidates,
            new SearchFilters(category: 'Обувь', price_max: 10000, attributes: ['color' => 'черный', 'material' => 'мембрана']),
            5,
        );

        $this->assertCount(2, $reranked);
        $this->assertSame(11, $reranked[0]['product_id']);
        $this->assertSame(10, $reranked[1]['product_id']);
    }

    public function testSearchSimilarSetsLocalHnswIterativeScanRelaxedOrderAndOnlyHardFiltersPriceMax(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $realEm */
        $realEm = self::getContainer()->get('doctrine.orm.entity_manager');

        /** @var list<string> $executedStatements */
        $executedStatements = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeast(2))->method('beginTransaction');
        $connection->expects($this->atLeast(2))->method('commit');
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 0;
            });

        /** @var list<string> $capturedDql */
        $capturedDql = [];
        $product = new ProductProjection(
            productId: 101,
            storeId: 1,
            name: 'Salomon',
            price: '12000.00',
            categoryName: 'Спорт',
            productUrl: '/products/101',
            attributes: ['color' => 'черный'],
            embedding: new Vector([0.1, 0.2]),
        );
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([[$product, 'similarity' => 0.88]]);

        $recordDql = static function (string $dql) use (&$capturedDql): void {
            $capturedDql[] = $dql;
        };

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $em->method('getClassMetadata')->willReturn($realEm->getClassMetadata(ProductProjection::class));
        $em->method('getConfiguration')->willReturn(new Configuration());
        $em->method('createQueryBuilder')->willReturnCallback(static fn (): QueryBuilder => new class($em, $query, $recordDql) extends QueryBuilder {
            /**
             * @param Query<mixed, mixed>   $stubQuery
             * @param Closure(string): void $recordDql
             */
            public function __construct(
                EntityManagerInterface $em,
                private readonly Query $stubQuery,
                private readonly Closure $recordDql,
            ) {
                parent::__construct($em);
            }

            public function getQuery(): Query
            {
                ($this->recordDql)($this->getDQL());

                return $this->stubQuery;
            }
        });

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repository = new ProductProjectionRepository($registry);
        $results = $repository->searchSimilar(
            [0.1, 0.2],
            1,
            new SearchFilters(category: 'Обувь', price_max: 15000, attributes: ['color' => 'черный']),
            5,
            0.57,
        );

        $this->assertContains("SET LOCAL hnsw.iterative_scan = 'relaxed_order'", $executedStatements);
        $this->assertNotEmpty($capturedDql);
        $this->assertStringContainsString('p.price <= :priceMax', $capturedDql[0]);
        $this->assertStringContainsString('p.isPublished = true', $capturedDql[0]);
        $this->assertStringContainsString('p.isInStock = true', $capturedDql[0]);
        $this->assertStringNotContainsString('categoryName', $capturedDql[0]);
        $this->assertStringNotContainsString('attributes', $capturedDql[0]);
        $this->assertCount(1, $results);
        $this->assertSame('0.8800', $results[0]['similarity']);
    }
}
