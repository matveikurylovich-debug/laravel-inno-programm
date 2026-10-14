<?php

namespace App\Services;

use App\Contracts\ProductIndex;
use App\Search\FacetParser;
use App\Search\SearchQueryBuilder;
use Illuminate\Support\Facades\Redis;

class SearchService
{
    public function __construct(
        private readonly ProductIndex $index,
        private readonly SearchQueryBuilder $queries,
        private readonly FacetParser $facets,
        private readonly SearchEventProducer $events,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}, facets: array<string, mixed>}
     */
    public function search(array $params): array
    {
        $cacheKey = 'search:cache:'.sha1((string) json_encode($params));
        $cached = Redis::get($cacheKey);

        if (is_string($cached)) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                $this->publish($params, (int) ($decoded['meta']['total'] ?? 0), 0);

                return $decoded;
            }
        }

        $started = microtime(true);
        $raw = $this->index->search($this->queries->build($params));
        $result = $this->facets->present($raw, $params);
        $tookMs = (int) round((microtime(true) - $started) * 1000);

        Redis::setex($cacheKey, 120, (string) json_encode($result));
        $this->publish($params, $result['meta']['total'], $tookMs);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function publish(array $params, int $total, int $tookMs): void
    {
        $filters = array_filter([
            'category_id' => $params['category_id'] ?? null,
            'price_min' => $params['price_min'] ?? null,
            'price_max' => $params['price_max'] ?? null,
            'in_stock' => $params['in_stock'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $this->events->performed(trim((string) ($params['q'] ?? '')), $filters, $total, $tookMs);
    }
}
