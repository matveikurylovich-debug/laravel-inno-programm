<?php

namespace App\Search;

class ProductDocument
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function fromProductEvent(array $payload): array
    {
        return [
            'id' => (string) ($payload['product_id'] ?? $payload['id'] ?? ''),
            'store_id' => (int) ($payload['store_id'] ?? 0),
            'name' => (string) ($payload['name'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'price' => (float) ($payload['price'] ?? 0),
            'is_in_stock' => $this->inStock($payload),
            'category_id' => isset($payload['category_id']) ? (int) $payload['category_id'] : null,
            'category_name' => (string) ($payload['category_name'] ?? ''),
            'attributes' => $this->attributes($payload['attributes'] ?? []),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function inStock(array $payload): bool
    {
        if (array_key_exists('available', $payload)) {
            return (int) $payload['available'] > 0;
        }

        if (array_key_exists('quantity', $payload)) {
            return (int) $payload['quantity'] > 0;
        }

        return (bool) ($payload['is_in_stock'] ?? $payload['is_active'] ?? false);
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
            if (is_array($value) && isset($value['key'], $value['value'])) {
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
