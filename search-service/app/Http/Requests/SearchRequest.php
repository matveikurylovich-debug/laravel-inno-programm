<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:relevance,price_asc,price_desc'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $page = max(1, (int) $this->input('page', 1));
                $perPage = min(100, max(1, (int) $this->input('per_page', 20)));
                if ($page * $perPage > 10000) {
                    $validator->errors()->add('page', 'Слишком глубокая страница поиска.');
                }
            },
        ];
    }
}
