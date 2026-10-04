<?php

namespace App\Http\Requests\Api\Product;

use App\Models\Category;
use App\Rules\ScalarAttributes;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'    => ['required', 'integer', 'exists:stores,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku'         => ['nullable', 'string', 'max:255'],
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'attributes'  => ['nullable', 'array', new ScalarAttributes],
            'is_active'   => ['nullable', 'boolean'],
            'quantity'    => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('category_id') || ! $this->filled('store_id')) {
                return;
            }

            $category = Category::query()->find($this->input('category_id'));
            if ($category && (int) $category->store_id !== (int) $this->input('store_id')) {
                $validator->errors()->add('category_id', 'Категория принадлежит другому магазину');
            }
        });
    }
}