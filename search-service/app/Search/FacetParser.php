<?php

namespace App\Search;

class FacetParser
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $params
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}, facets: array<string, mixed>}
     */
    public function present(array $raw, array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $hits = $raw['hits']['hits'] ?? [];
        $total = $raw['hits']['total']['value'] ?? $raw['hits']['total'] ?? 0;

        $data = [];
        foreach ($hits as $hit) {
            if (is_array($hit) && isset($hit['_source']) && is_array($hit['_source'])) {
                $data[] = $hit['_source'];
            }
        }

        $aggregations = is_array($raw['aggregations'] ?? null) ? $raw['aggregations'] : [];

        return [
            'data' => $data,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
            'facets' => [
                'categories' => $this->categories($aggregations),
                'in_stock' => [
                    'true' => (int) ($aggregations['in_stock_count']['buckets']['true']['doc_count'] ?? 0),
                    'false' => (int) ($aggregations['in_stock_count']['buckets']['false']['doc_count'] ?? 0),
                ],
                'price' => [
                    'min' => $aggregations['price_range']['min'] ?? null,
                    'max' => $aggregations['price_range']['max'] ?? null,
                ],
                'attributes' => $this->attributes($aggregations),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $aggregations
     * @return list<array{id: int|string, name: string, count: int}>
     */
    private function categories(array $aggregations): array
    {
        $categories = [];

        foreach ($aggregations['categories']['buckets'] ?? [] as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            $categories[] = [
                'id' => $bucket['key'],
                'name' => (string) ($bucket['name']['buckets'][0]['key'] ?? ''),
                'count' => (int) ($bucket['doc_count'] ?? 0),
            ];
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $aggregations
     * @return list<array{key: string, values: list<array{value: string, count: int}>}>
     */
    private function attributes(array $aggregations): array
    {
        $attributes = [];

        foreach ($aggregations['attributes']['keys']['buckets'] ?? [] as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            $values = [];
            foreach ($bucket['values']['buckets'] ?? [] as $value) {
                if (! is_array($value)) {
                    continue;
                }

                $values[] = [
                    'value' => (string) $value['key'],
                    'count' => (int) ($value['doc_count'] ?? 0),
                ];
            }

            $attributes[] = [
                'key' => (string) $bucket['key'],
                'values' => $values,
            ];
        }

        return $attributes;
    }
}
