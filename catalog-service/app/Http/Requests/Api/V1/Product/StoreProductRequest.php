<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use App\Models\Product;
use App\Rules\ProductRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && is_string($this->input('name')) && $this->input('name') !== '') {
            $generated = Product::slugFromName($this->input('name'));
            if ($generated !== '') {
                $this->merge(['slug' => $generated]);
            }
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rawStoreId = $this->input('store_id');
        $storeId = is_numeric($rawStoreId) ? (int) $rawStoreId : null;

        return ProductRules::forCreate($storeId);
    }
}
