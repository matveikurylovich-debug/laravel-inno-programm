<?php

namespace App\Http\Requests\Api\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'  => ['required', 'integer', 'exists:stores,id'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['nullable', 'string', 'max:255'],
            'position'  => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('parent_id') || ! $this->filled('store_id')) {
                return;
            }

            $parent = Category::query()->find($this->input('parent_id'));
            if ($parent && (int) $parent->store_id !== (int) $this->input('store_id')) {
                $validator->errors()->add('parent_id', 'Родительская категория принадлежит другому магазину');
            }
        });
    }
}