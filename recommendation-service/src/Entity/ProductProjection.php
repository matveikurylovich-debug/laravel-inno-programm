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
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(name: 'product_id', type: Types::INTEGER)]
    private int $productId;

    #[ORM\Column(name: 'store_id', type: Types::INTEGER)]
    private int $storeId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price;

    #[ORM\Column(name: 'category_name', length: 150)]
    private string $categoryName;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $attributes = [];

    #[ORM\Column(name: 'is_published', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isPublished = true;

    #[ORM\Column(name: 'is_in_stock', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isInStock = true;

    #[ORM\Column(name: 'product_url', length: 512)]
    private string $productUrl;

    #[ORM\Column(type: 'vector', length: 768, nullable: true)]
    private ?Vector $embedding = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        int $productId,
        int $storeId,
        string $name,
        string $price,
        string $categoryName,
        string $productUrl,
    ) {
        $this->productId = $productId;
        $this->storeId = $storeId;
        $this->name = $name;
        $this->price = $price;
        $this->categoryName = $categoryName;
        $this->productUrl = $productUrl;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function setStoreId(int $storeId): self
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getCategoryName(): string
    {
        return $this->categoryName;
    }

    public function setCategoryName(string $categoryName): self
    {
        $this->categoryName = $categoryName;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function isInStock(): bool
    {
        return $this->isInStock;
    }

    public function setIsInStock(bool $isInStock): self
    {
        $this->isInStock = $isInStock;

        return $this;
    }

    public function getProductUrl(): string
    {
        return $this->productUrl;
    }

    public function setProductUrl(string $productUrl): self
    {
        $this->productUrl = $productUrl;

        return $this;
    }

    public function getEmbedding(): ?Vector
    {
        return $this->embedding;
    }

    public function setEmbedding(?Vector $embedding): self
    {
        $this->embedding = $embedding;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
