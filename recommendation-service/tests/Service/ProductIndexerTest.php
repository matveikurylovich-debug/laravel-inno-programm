<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Command\ReindexProductsCommand;
use App\Contracts\LlmClientInterface;
use App\Dto\CatalogProductEvent;
use App\Entity\ProductProjection;
use App\Repository\ProductProjectionRepository;
use App\Service\ProductIndexer;
use App\Service\ProductTextBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ProductIndexerTest extends TestCase
{
    public function testIndexFormatsProductTextAndUpsertsEmbedding(): void
    {
        $llm = $this->createMock(LlmClientInterface::class);
        $llm->expects($this->once())
            ->method('createEmbedding')
            ->with(
                "Категория: Обувь\nНазвание: Salomon Gore-Tex\nОписание: Зимние кроссовки\nХарактеристики: color: черный",
                'RETRIEVAL_DOCUMENT',
            )
            ->willReturn([0.25, 0.5, 0.75]);

        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects($this->once())
            ->method('upsertProjection')
            ->with(self::callback(static fn (CatalogProductEvent $event): bool => 101 === $event->product_id
                && 1 === $event->store_id
                && $event->embedding === [0.25, 0.5, 0.75]));

        $indexer = new ProductIndexer(new ProductTextBuilder(), $llm, $repository);
        $indexer->index(new CatalogProductEvent(
            product_id: 101,
            store_id: 1,
            name: 'Salomon Gore-Tex',
            description: 'Зимние кроссовки',
            category: 'Обувь',
            price: 14500.00,
            is_published: true,
            is_in_stock: true,
            product_url: '/products/101',
            attributes: ['color' => 'черный'],
        ));
    }

    public function testReindexCommandUsesProductIndexerPipeline(): void
    {
        $projection = new ProductProjection(
            productId: 55,
            storeId: 3,
            name: 'Смартфон X',
            price: '600.00',
            categoryName: 'Смартфоны',
            productUrl: '/products/55',
            description: 'Флагман',
            attributes: ['brand' => 'Acme'],
            isPublished: true,
            isInStock: false,
        );

        $llm = $this->createMock(LlmClientInterface::class);
        $llm->expects($this->once())
            ->method('createEmbedding')
            ->willReturn([0.1, 0.9]);

        $repository = $this->createMock(ProductProjectionRepository::class);
        $repository->expects($this->once())
            ->method('upsertProjection')
            ->with(self::callback(static fn (CatalogProductEvent $dto): bool => 55 === $dto->product_id
                && 3 === $dto->store_id
                && 600.0 === $dto->price
                && $dto->is_published
                && false === $dto->is_in_stock
                && $dto->attributes === ['brand' => 'Acme']
                && $dto->embedding === [0.1, 0.9]));

        $query = $this->createStub(Query::class);
        $query->method('toIterable')->willReturn([$projection]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('createQuery')->willReturn($query);
        $em->expects($this->once())->method('detach')->with($projection);

        $command = new ReindexProductsCommand(
            $em,
            new ProductIndexer(new ProductTextBuilder(), $llm, $repository),
        );
        $tester = new CommandTester($command);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Reindexed 1 products.', $tester->getDisplay());
    }
}
