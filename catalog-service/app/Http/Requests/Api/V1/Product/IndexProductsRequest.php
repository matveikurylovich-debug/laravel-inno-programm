<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductsRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array{category_id: int|null, page: int, per_page: int}
     */
    public function normalizedFilters(): array
    {
        /** @var array{category_id?: int|string|null, page?: int|string|null, per_page?: int|string|null} $validated */
        $validated = $this->validated();

        return [
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'page' => isset($validated['page']) ? (int) $validated['page'] : 1,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : 20,
        ];
    }
}
