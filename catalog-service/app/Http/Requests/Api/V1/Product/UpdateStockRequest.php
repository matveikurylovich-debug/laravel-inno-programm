<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class UpdateStockRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $product = $this->productRecord();

        return [
            'quantity' => ['required', 'integer', 'min:0'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('store_locations', 'id')->where('store_id', $product->store_id),
            ],
        ];
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
