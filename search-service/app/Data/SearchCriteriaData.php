<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\SearchSort;

final readonly class SearchCriteriaData
{
    public function __construct(
        public string $query = '',
        public ?int $categoryId = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
        public ?bool $inStock = null,
        public int $page = 1,
        public int $perPage = 20,
        public SearchSort $sort = SearchSort::Relevance,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public static function fromArray(array $params): self
    {
        $sort = $params['sort'] ?? null;
        $resolvedSort = $sort instanceof SearchSort
            ? $sort
            : (is_string($sort) ? (SearchSort::tryFrom($sort) ?? SearchSort::Relevance) : SearchSort::Relevance);

        $inStock = null;
        if (array_key_exists('in_stock', $params) && $params['in_stock'] !== null && $params['in_stock'] !== '') {
            $inStock = filter_var($params['in_stock'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $rawQuery = $params['q'] ?? '';
        $rawCategoryId = $params['category_id'] ?? null;
        $rawPriceMin = $params['price_min'] ?? null;
        $rawPriceMax = $params['price_max'] ?? null;
        $rawPage = $params['page'] ?? 1;
        $rawPerPage = $params['per_page'] ?? 20;

        return new self(
            query: is_scalar($rawQuery) ? trim((string) $rawQuery) : '',
            categoryId: is_numeric($rawCategoryId) ? (int) $rawCategoryId : null,
            priceMin: is_numeric($rawPriceMin) ? (float) $rawPriceMin : null,
            priceMax: is_numeric($rawPriceMax) ? (float) $rawPriceMax : null,
            inStock: $inStock,
            page: max(1, is_numeric($rawPage) ? (int) $rawPage : 1),
            perPage: min(100, max(1, is_numeric($rawPerPage) ? (int) $rawPerPage : 20)),
            sort: $resolvedSort,
        );
    }

    /**
     * @return array{q: string, category_id: int|null, price_min: float|null, price_max: float|null, in_stock: bool|null, page: int, per_page: int, sort: string}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->query,
            'category_id' => $this->categoryId,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'in_stock' => $this->inStock,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'sort' => $this->sort->value,
        ];
    }

    /**
     * @return array<string, int|float|bool>
     */
    public function filters(): array
    {
        return array_filter([
            'category_id' => $this->categoryId,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'in_stock' => $this->inStock,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
