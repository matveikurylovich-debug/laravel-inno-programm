<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && is_string($this->input('name')) && $this->input('name') !== '') {
            $generated = Category::slugFromName($this->input('name'));
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

        $slugRules = ['nullable', 'string', 'max:255'];
        if ($storeId !== null) {
            $slugRules[] = Rule::unique('categories', 'slug')->where('store_id', $storeId);
        }

        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => $slugRules,
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('parent_id') || ! $this->filled('store_id')) {
                return;
            }

            if (! is_numeric($this->input('parent_id')) || ! is_numeric($this->input('store_id'))) {
                return;
            }

            $parent = Category::query()->find((int) $this->input('parent_id'));
            if ($parent !== null && (int) $parent->store_id !== (int) $this->input('store_id')) {
                $validator->errors()->add('parent_id', 'Родительская категория принадлежит другому магазину');
            }
        });
    }
}
