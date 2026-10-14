<?php

namespace App\Search;

class SearchQueryBuilder
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function build(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));

        return [
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
            'sort' => $this->sort((string) ($params['sort'] ?? 'relevance')),
            'query' => [
                'bool' => [
                    'must' => $this->must($params),
                    'filter' => $this->filters($params),
                ],
            ],
            'aggs' => $this->aggregations(),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function must(array $params): array
    {
        $query = trim((string) ($params['q'] ?? ''));

        if ($query === '') {
            return [['match_all' => new \stdClass]];
        }

        return [[
            'multi_match' => [
                'query' => $query,
                'fields' => ['name^3', 'description'],
            ],
        ]];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function filters(array $params): array
    {
        $filters = [];

        if (! empty($params['category_id'])) {
            $filters[] = ['term' => ['category_id' => (int) $params['category_id']]];
        }

        $range = [];
        if (isset($params['price_min']) && $params['price_min'] !== '') {
            $range['gte'] = (float) $params['price_min'];
        }
        if (isset($params['price_max']) && $params['price_max'] !== '') {
            $range['lte'] = (float) $params['price_max'];
        }
        if ($range !== []) {
            $filters[] = ['range' => ['price' => $range]];
        }

        if (array_key_exists('in_stock', $params) && $params['in_stock'] !== null && $params['in_stock'] !== '') {
            $filters[] = ['term' => ['is_in_stock' => filter_var($params['in_stock'], FILTER_VALIDATE_BOOLEAN)]];
        }

        return $filters;
    }

    /**
     * @return list<array<string, string>>
     */
    private function sort(string $sort): array
    {
        return match ($sort) {
            'price_asc' => [['price' => 'asc']],
            'price_desc' => [['price' => 'desc']],
            default => [['_score' => 'desc']],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregations(): array
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
