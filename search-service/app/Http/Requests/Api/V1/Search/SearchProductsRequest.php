<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Search;

use App\Data\SearchCriteriaData;
use App\Enums\SearchSort;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchProductsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('price_min') && is_numeric($this->input('price_min')), 'gte:price_min')],
            'in_stock' => ['nullable', Rule::in([true, false, 0, 1, '0', '1', 'true', 'false'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', Rule::enum(SearchSort::class)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rawPage = $this->input('page', 1);
                $rawPerPage = $this->input('per_page', 20);
                $page = max(1, is_numeric($rawPage) ? (int) $rawPage : 1);
                $perPage = min(100, max(1, is_numeric($rawPerPage) ? (int) $rawPerPage : 20));
                if ($page * $perPage > 10000) {
                    $validator->errors()->add('page', 'Слишком глубокая страница поиска.');
                }
            },
        ];
    }

    public function criteria(): SearchCriteriaData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return SearchCriteriaData::fromArray($validated);
    }
}
