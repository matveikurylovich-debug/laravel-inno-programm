<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductProjectionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pgvector\Vector;

#[ORM\Entity(repositoryClass: ProductProjectionRepository::class)]
#[ORM\Table(name: 'product_projections')]
#[ORM\Index(name: 'idx_products_store_stock', columns: ['store_id', 'is_published', 'is_in_stock'])]
class ProductProjection
{
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'NONE')]
        #[ORM\Column(name: 'product_id', type: Types::INTEGER)]
        private int $productId,
        #[ORM\Column(name: 'store_id', type: Types::INTEGER)]
        private int $storeId,
        #[ORM\Column(length: 255)]
        private string $name,
        #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
        private string $price,
        #[ORM\Column(name: 'category_name', length: 150)]
        private string $categoryName,
        #[ORM\Column(name: 'product_url', length: 512)]
        private string $productUrl,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $description = null,
        #[ORM\Column(type: Types::JSON)]
        private array $attributes = [],
        #[ORM\Column(name: 'is_published', type: Types::BOOLEAN, options: ['default' => true])]
        private bool $isPublished = true,
        #[ORM\Column(name: 'is_in_stock', type: Types::BOOLEAN, options: ['default' => true])]
        private bool $isInStock = true,
        #[ORM\Column(type: 'vector', nullable: true)]
        private ?Vector $embedding = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getCategoryName(): string
    {
        return $this->categoryName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function isInStock(): bool
    {
        return $this->isInStock;
    }

    public function getProductUrl(): string
    {
        return $this->productUrl;
    }

    public function getEmbedding(): ?Vector
    {
        return $this->embedding;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
