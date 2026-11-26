<?php

declare(strict_types=1);

namespace App\Service;

use App\Contracts\LlmClientInterface;
use App\Dto\CatalogProductEvent;
use App\Entity\ProductProjection;
use App\Repository\ProductProjectionRepository;

final readonly class ProductIndexer
{
    public function __construct(
        private ProductTextBuilder $textBuilder,
        private LlmClientInterface $llmClient,
        private ProductProjectionRepository $repository,
    ) {
    }

    public function index(CatalogProductEvent $product): void
    {
        $text = $this->textBuilder->build(
            $product->name,
            $product->description,
            $product->category,
            $product->attributes,
        );
        $embedding = $this->llmClient->createEmbedding($text);

        $this->repository->upsertProjection($product->withEmbedding($embedding));
    }

    public function reindexProjection(ProductProjection $projection): void
    {
        $this->index(new CatalogProductEvent(
            product_id: $projection->getProductId(),
            store_id: $projection->getStoreId(),
            name: $projection->getName(),
            description: $projection->getDescription(),
            category: $projection->getCategoryName(),
            price: (float) $projection->getPrice(),
            is_published: $projection->isPublished(),
            is_in_stock: $projection->isInStock(),
            product_url: $projection->getProductUrl(),
            attributes: $projection->getAttributes(),
        ));
    }
}
