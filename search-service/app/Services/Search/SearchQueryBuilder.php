<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\SearchCriteriaData;
use App\Enums\SearchSort;
use stdClass;

class SearchQueryBuilder
{
    /**
     * @param  SearchCriteriaData|array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    public function build(SearchCriteriaData|array $criteria): array
    {
        $criteria = is_array($criteria) ? SearchCriteriaData::fromArray($criteria) : $criteria;

        return [
            'timeout' => '2s',
            'from' => ($criteria->page - 1) * $criteria->perPage,
            'size' => $criteria->perPage,
            'sort' => $this->buildSort($criteria->sort),
            'query' => [
                'bool' => [
                    'must' => $this->buildTextMatch($criteria->query),
                    'filter' => $this->buildFilters($criteria),
                ],
            ],
            'aggs' => $this->buildAggregations(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTextMatch(string $query): array
    {
        if ($query === '') {
            return [['match_all' => new stdClass]];
        }

        if (preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s\-]*$/u', $query) !== 1) {
            return [['match_none' => new stdClass]];
        }

        $clauses = [
            ['match' => ['name' => ['query' => $query, 'boost' => 5]]],
            [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['name^3', 'description', 'category_name.text^2'],
                    'fuzziness' => 'AUTO',
                ],
            ],
            [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['name.syn^3', 'description.syn', 'category_name.syn^2'],
                ],
            ],
            ['match' => ['name.prefix' => ['query' => $query]]],
            $this->buildNestedAttributeMatch($query),
        ];

        return [[
            'bool' => [
                'should' => $clauses,
                'minimum_should_match' => 1,
            ],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNestedAttributeMatch(string $query): array
    {
        $should = [
            ['match' => ['attributes.value.text' => ['query' => $query, 'fuzziness' => 'AUTO']]],
            ['match' => ['attributes.value.syn' => ['query' => $query]]],
        ];

        return [
            'nested' => [
                'path' => 'attributes',
                'query' => ['bool' => ['should' => $should]],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildFilters(SearchCriteriaData $criteria): array
    {
        $filters = [];

        if ($criteria->categoryId !== null && $criteria->categoryId > 0) {
            $filters[] = ['term' => ['category_path' => $criteria->categoryId]];
        }

        $priceRange = $this->buildPriceRange($criteria);
        if ($priceRange !== null) {
            $filters[] = $priceRange;
        }

        if ($criteria->inStock !== null) {
            $filters[] = ['term' => ['is_in_stock' => $criteria->inStock]];
        }

        return $filters;
    }

    /**
     * @return array{range: array{price: array<string, float>}}|null
     */
    private function buildPriceRange(SearchCriteriaData $criteria): ?array
    {
        $range = [];

        if ($criteria->priceMin !== null) {
            $range['gte'] = $criteria->priceMin;
        }

        if ($criteria->priceMax !== null) {
            $range['lte'] = $criteria->priceMax;
        }

        if ($range === []) {
            return null;
        }

        return ['range' => ['price' => $range]];
    }

    /**
     * @return list<array<string, string>>
     */
    private function buildSort(SearchSort $sort): array
    {
        return match ($sort) {
            SearchSort::PriceAsc => [['price' => 'asc']],
            SearchSort::PriceDesc => [['price' => 'desc']],
            SearchSort::Relevance => [['_score' => 'desc']],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAggregations(): array
    {
        return [
            'categories' => [
                'terms' => ['field' => 'category_id', 'size' => 50],
                'aggs' => [
                    'name' => ['terms' => ['field' => 'category_name', 'size' => 1]],
                ],
            ],
            'in_stock_count' => [
                'filters' => [
                    'filters' => [
                        'true' => ['term' => ['is_in_stock' => true]],
                        'false' => ['term' => ['is_in_stock' => false]],
                    ],
                ],
            ],
            'price_range' => [
                'stats' => ['field' => 'price'],
            ],
            'attributes' => [
                'nested' => ['path' => 'attributes'],
                'aggs' => [
                    'keys' => [
                        'terms' => ['field' => 'attributes.key', 'size' => 20],
                        'aggs' => [
                            'values' => ['terms' => ['field' => 'attributes.value', 'size' => 20]],
                        ],
                    ],
                ],
            ],
        ];
    }
}
