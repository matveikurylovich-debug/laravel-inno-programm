<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class CatalogProductEvent
{
    /**
     * @param array<string, mixed> $attributes
     * @param list<float>|null     $embedding
     */
    public function __construct(
        public int $product_id,
        public int $store_id,
        public string $name,
        public ?string $description,
        public string $category,
        public float $price,
        public bool $is_published,
        public bool $is_in_stock,
        public string $product_url,
        public array $attributes = [],
        public ?array $embedding = null,
    ) {
    }

    /**
     * @param list<float> $embedding
     */
    public function withEmbedding(array $embedding): self
    {
        return new self(
            $this->product_id,
            $this->store_id,
            $this->name,
            $this->description,
            $this->category,
            $this->price,
            $this->is_published,
            $this->is_in_stock,
            $this->product_url,
            $this->attributes,
            $embedding,
        );
    }
}
