<?php

declare(strict_types=1);

namespace App\Dto;

use JsonException;
use stdClass;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

#[Context(
    normalizationContext: [
        AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
    ],
)]
final readonly class ChatResponse
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $products;

    /**
     * @param list<array<string, mixed>> $products
     */
    public function __construct(
        public string $conversation_id,
        public string $reply,
        array $products,
        public SearchFilters $filters,
    ) {
        $this->products = $this->normalizeProducts($products);
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeProducts(array $products): array
    {
        $normalized = [];
        foreach ($products as $product) {
            $product['attributes'] = $this->normalizeAttributes($product['attributes'] ?? null);
            $normalized[] = $product;
        }

        return $normalized;
    }

    private function normalizeAttributes(mixed $attributes): stdClass
    {
        if ($attributes instanceof stdClass) {
            return $attributes;
        }

        if (\is_string($attributes)) {
            try {
                $attributes = json_decode($attributes, true, 512, \JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return new stdClass();
            }
        }

        if (!\is_array($attributes)) {
            return new stdClass();
        }

        $object = new stdClass();
        foreach ($attributes as $key => $value) {
            if (\is_string($key) && '' !== $key) {
                $object->{$key} = $value;
            }
        }

        return $object;
    }
}
