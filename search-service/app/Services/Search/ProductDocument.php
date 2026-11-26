<?php

declare(strict_types=1);

namespace App\Services\Search;

use Carbon\CarbonImmutable;

class ProductDocument
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fromProductEvent(array $payload): array
    {
        $rawId = $payload['product_id'] ?? $payload['id'] ?? '';
        $rawStoreId = $payload['store_id'] ?? 0;
        $rawName = $payload['name'] ?? '';
        $rawDescription = $payload['description'] ?? '';
        $rawPrice = $payload['price'] ?? 0;
        $rawCategoryId = $payload['category_id'] ?? null;
        $rawCategoryName = $payload['category_name'] ?? '';

        return [
            'id' => is_scalar($rawId) ? (string) $rawId : '',
            'store_id' => is_numeric($rawStoreId) ? (int) $rawStoreId : 0,
            'name' => is_scalar($rawName) ? (string) $rawName : '',
            'description' => is_scalar($rawDescription) ? (string) $rawDescription : '',
            'price' => is_numeric($rawPrice) ? (float) $rawPrice : 0.0,
            'is_in_stock' => $this->inStock($payload),
            'category_id' => is_numeric($rawCategoryId) ? (int) $rawCategoryId : null,
            'category_path' => $this->categoryPath($payload),
            'category_name' => is_scalar($rawCategoryName) ? (string) $rawCategoryName : '',
            'attributes' => $this->attributes($payload['attributes'] ?? []),
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function inStock(array $payload): bool
    {
        if (array_key_exists('available', $payload)) {
            return is_numeric($payload['available']) && (int) $payload['available'] > 0;
        }

        if (array_key_exists('quantity', $payload)) {
            return is_numeric($payload['quantity']) && (int) $payload['quantity'] > 0;
        }

        return (bool) ($payload['is_in_stock'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>
     */
    private function categoryPath(array $payload): array
    {
        if (isset($payload['category_path']) && is_array($payload['category_path'])) {
            $path = [];
            foreach ($payload['category_path'] as $item) {
                if (is_numeric($item)) {
                    $path[] = (int) $item;
                }
            }

            return $path;
        }

        if (isset($payload['category_id']) && is_numeric($payload['category_id'])) {
            return [(int) $payload['category_id']];
        }

        return [];
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function attributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $pairs = [];

        foreach ($attributes as $key => $value) {
            if (is_array($value) && isset($value['key'], $value['value']) && is_scalar($value['key']) && is_scalar($value['value'])) {
                $pairs[] = [
                    'key' => (string) $value['key'],
                    'value' => (string) $value['value'],
                ];

                continue;
            }

            if (is_scalar($value)) {
                $pairs[] = [
                    'key' => (string) $key,
                    'value' => (string) $value,
                ];
            }
        }

        return $pairs;
    }
}
