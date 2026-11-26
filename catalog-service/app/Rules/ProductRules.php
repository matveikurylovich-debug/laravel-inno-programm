<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Product;
use Illuminate\Validation\Rule;

final class ProductRules
{
    /**
     * @return list<string>
     */
    public static function storeId(): array
    {
        return ['required', 'integer', 'exists:stores,id'];
    }

    /**
     * @return list<mixed>
     */
    public static function category(?int $storeId, bool $required = true): array
    {
        $exists = Rule::exists('categories', 'id');

        if ($storeId !== null) {
            $exists = $exists->where('store_id', $storeId);
        }

        return $required
            ? ['required', 'integer', $exists]
            : ['nullable', 'integer', $exists];
    }

    /**
     * @return list<mixed>
     */
    public static function sku(?int $storeId, ?int $ignoreProductId = null): array
    {
        $rules = ['nullable', 'string', 'max:255'];

        if ($storeId !== null) {
            $unique = Rule::unique('products', 'sku')->where('store_id', $storeId);

            if ($ignoreProductId !== null) {
                $unique = $unique->ignore($ignoreProductId);
            }

            $rules[] = $unique;
        }

        return $rules;
    }

    /**
     * @return list<mixed>
     */
    public static function slug(?int $storeId, ?int $ignoreProductId = null): array
    {
        $rules = ['nullable', 'string', 'max:255'];

        if ($storeId !== null) {
            $unique = Rule::unique('products', 'slug')->where('store_id', $storeId);

            if ($ignoreProductId !== null) {
                $unique = $unique->ignore($ignoreProductId);
            }

            $rules[] = $unique;
        }

        return $rules;
    }

    /**
     * @return list<string>
     */
    public static function name(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return list<string>
     */
    public static function description(): array
    {
        return ['nullable', 'string'];
    }

    /**
     * @return list<string>
     */
    public static function price(): array
    {
        return ['required', 'numeric', 'min:0'];
    }

    /**
     * @return list<mixed>
     */
    public static function attributes(): array
    {
        return ['nullable', 'array', new ScalarAttributes];
    }

    /**
     * @return list<string>
     */
    public static function isActive(): array
    {
        return ['nullable', 'boolean'];
    }

    /**
     * @return list<string>
     */
    public static function quantity(): array
    {
        return ['nullable', 'integer', 'min:0'];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function forCreate(?int $storeId): array
    {
        return [
            'store_id' => self::storeId(),
            'category_id' => self::category($storeId, required: true),
            'sku' => self::sku($storeId),
            'name' => self::name(),
            'slug' => self::slug($storeId),
            'description' => self::description(),
            'price' => self::price(),
            'attributes' => self::attributes(),
            'is_active' => self::isActive(),
            'quantity' => self::quantity(),
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function forUpdate(Product $product): array
    {
        $storeId = (int) $product->store_id;
        $productId = (int) $product->id;

        return [
            'category_id' => ['sometimes', ...self::category($storeId, required: false)],
            'sku' => ['sometimes', ...self::sku($storeId, $productId)],
            'name' => ['sometimes', ...self::name()],
            'slug' => ['sometimes', ...self::slug($storeId, $productId)],
            'description' => self::description(),
            'price' => ['sometimes', ...self::price()],
            'attributes' => self::attributes(),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
