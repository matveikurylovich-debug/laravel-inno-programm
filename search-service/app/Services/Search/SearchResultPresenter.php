<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Data\SearchCriteriaData;

class SearchResultPresenter
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  SearchCriteriaData|array<string, mixed>  $criteria
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}, facets: array<string, mixed>}
     */
    public function present(array $raw, SearchCriteriaData|array $criteria = []): array
    {
        $criteria = is_array($criteria) ? SearchCriteriaData::fromArray($criteria) : $criteria;
        $hitsEnvelope = is_array($raw['hits'] ?? null) ? $raw['hits'] : [];
        $hits = is_array($hitsEnvelope['hits'] ?? null) ? $hitsEnvelope['hits'] : [];
        $totalMeta = $hitsEnvelope['total'] ?? 0;
        $rawTotal = is_array($totalMeta) ? ($totalMeta['value'] ?? 0) : $totalMeta;
        $total = is_numeric($rawTotal) ? (int) $rawTotal : 0;

        $data = [];
        foreach ($hits as $hit) {
            if (is_array($hit) && isset($hit['_source']) && is_array($hit['_source'])) {
                /** @var array<string, mixed> $source */
                $source = $hit['_source'];
                $data[] = $source;
            }
        }

        /** @var array<string, mixed> $aggregations */
        $aggregations = is_array($raw['aggregations'] ?? null) ? $raw['aggregations'] : [];
        $inStockBuckets = $this->nestedArray($this->nestedArray($aggregations, 'in_stock_count'), 'buckets');
        $trueBucket = $this->nestedArray($inStockBuckets, 'true');
        $falseBucket = $this->nestedArray($inStockBuckets, 'false');
        $priceRange = $this->nestedArray($aggregations, 'price_range');

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $criteria->page,
                'per_page' => $criteria->perPage,
            ],
            'facets' => [
                'categories' => $this->categories($aggregations),
                'in_stock' => [
                    'true' => is_numeric($trueBucket['doc_count'] ?? null) ? (int) $trueBucket['doc_count'] : 0,
                    'false' => is_numeric($falseBucket['doc_count'] ?? null) ? (int) $falseBucket['doc_count'] : 0,
                ],
                'price' => [
                    'min' => $priceRange['min'] ?? null,
                    'max' => $priceRange['max'] ?? null,
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
        $buckets = $this->nestedArray($aggregations, 'categories')['buckets'] ?? [];
        if (! is_array($buckets)) {
            return [];
        }

        foreach ($buckets as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            $key = $bucket['key'] ?? '';
            $id = is_int($key) ? $key : (is_scalar($key) ? (string) $key : '');
            $nameBuckets = $this->nestedArray($bucket, 'name')['buckets'] ?? [];
            $firstNameBucket = is_array($nameBuckets) && is_array($nameBuckets[0] ?? null) ? $nameBuckets[0] : [];
            $rawName = $firstNameBucket['key'] ?? '';

            $categories[] = [
                'id' => $id,
                'name' => is_scalar($rawName) ? (string) $rawName : '',
                'count' => is_numeric($bucket['doc_count'] ?? null) ? (int) $bucket['doc_count'] : 0,
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
        $keyBuckets = $this->nestedArray($this->nestedArray($aggregations, 'attributes'), 'keys')['buckets'] ?? [];
        if (! is_array($keyBuckets)) {
            return [];
        }

        foreach ($keyBuckets as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            $values = [];
            $valueBuckets = $this->nestedArray($bucket, 'values')['buckets'] ?? [];
            if (is_array($valueBuckets)) {
                foreach ($valueBuckets as $value) {
                    if (! is_array($value)) {
                        continue;
                    }

                    $rawValueKey = $value['key'] ?? '';
                    $values[] = [
                        'value' => is_scalar($rawValueKey) ? (string) $rawValueKey : '',
                        'count' => is_numeric($value['doc_count'] ?? null) ? (int) $value['doc_count'] : 0,
                    ];
                }
            }

            $rawBucketKey = $bucket['key'] ?? '';
            $attributes[] = [
                'key' => is_scalar($rawBucketKey) ? (string) $rawBucketKey : '',
                'values' => $values,
            ];
        }

        return $attributes;
    }

    /**
     * @param  array<array-key, mixed>  $source
     * @return array<string, mixed>
     */
    private function nestedArray(array $source, string $key): array
    {
        $nested = $source[$key] ?? null;

        /** @var array<string, mixed> $result */
        $result = is_array($nested) ? $nested : [];

        return $result;
    }
}
