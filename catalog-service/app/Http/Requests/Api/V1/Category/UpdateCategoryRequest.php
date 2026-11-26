<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $category = $this->categoryRecord();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')
                    ->where('store_id', $category->store_id)
                    ->ignore($category->id),
            ],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('parent_id') || ! is_numeric($this->input('parent_id'))) {
                return;
            }

            $category = $this->categoryRecord();
            $parentId = (int) $this->input('parent_id');
            $storeId = (int) $category->store_id;

            $parent = Category::query()->find($parentId);
            if ($parent === null) {
                return;
            }

            if ((int) $parent->store_id !== $storeId) {
                $validator->errors()->add('parent_id', 'Родительская категория принадлежит другому магазину');

                return;
            }

            $forbiddenIds = Category::idsIncludingDescendants($storeId, (int) $category->id);
            if (in_array($parentId, $forbiddenIds, true)) {
                $validator->errors()->add('parent_id', 'Нельзя выбрать текущую категорию или её потомка в качестве родителя');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitized(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        if (array_key_exists('slug', $validated) && (! is_string($validated['slug']) || trim($validated['slug']) === '')) {
            unset($validated['slug']);
        }

        return $validated;
    }

    private function categoryRecord(): Category
    {
        $category = $this->route('category');

        if (! $category instanceof Category) {
            throw new LogicException('Route parameter [category] must be a Category instance.');
        }

        return $category;
    }
}
