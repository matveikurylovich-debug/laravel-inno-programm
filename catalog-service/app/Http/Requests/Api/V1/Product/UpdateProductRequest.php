<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use App\Models\Product;
use App\Rules\ProductRules;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

class UpdateProductRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ProductRules::forUpdate($this->productRecord());
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitized(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        foreach (['slug', 'sku'] as $field) {
            if (array_key_exists($field, $validated) && (! is_string($validated[$field]) || trim($validated[$field]) === '')) {
                unset($validated[$field]);
            }
        }

        return $validated;
    }

    private function productRecord(): Product
    {
        $product = $this->route('product');

        if (! $product instanceof Product) {
            throw new LogicException('Route parameter [product] must be a Product instance.');
        }

        return $product;
    }
}
