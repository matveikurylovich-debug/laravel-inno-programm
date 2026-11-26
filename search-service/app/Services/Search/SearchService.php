<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Contracts\ProductIndex;
use App\Data\SearchCriteriaData;
use App\Exceptions\SearchFailedException;
use App\Exceptions\SearchUnavailableException;
use App\Messaging\Producers\SearchEventsProducer;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Cache\Repository;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

class SearchService
{
    public function __construct(
        private readonly ProductIndex $index,
        private readonly SearchQueryBuilder $queries,
        private readonly SearchResultPresenter $facets,
        private readonly SearchEventsProducer $events,
        private readonly Repository $cache,
        #[Config('elasticsearch.cache_ttl', 120)]
        private readonly int $cacheTtlSeconds = 120,
    ) {}

    /**
     * @param  SearchCriteriaData|array<string, mixed>  $criteria
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}, facets: array<string, mixed>}
     */
    public function search(SearchCriteriaData|array $criteria): array
    {
        $criteria = is_array($criteria) ? SearchCriteriaData::fromArray($criteria) : $criteria;
        $cacheKey = 'search:cache:'.sha1(json_encode($criteria->toArray(), JSON_THROW_ON_ERROR));

        $cached = null;
        try {
            $cached = $this->cache->get($cacheKey);
        } catch (Throwable $exception) {
            report($exception);
        }

        if (is_array($cached) && isset($cached['data'], $cached['meta'], $cached['facets']) && is_array($cached['meta'])) {
            $total = is_numeric($cached['meta']['total'] ?? null) ? (int) $cached['meta']['total'] : 0;
            $this->publish($criteria, $total, 0);

            /** @var array{data: list<array<string, mixed>>, meta: array{total: int, page: int, per_page: int}, facets: array<string, mixed>} $cached */
            return $cached;
        }

        try {
            $raw = $this->index->search($this->queries->build($criteria));
        } catch (SearchUnavailableException $exception) {
            report($exception);
            throw new ServiceUnavailableHttpException(null, 'Поиск недоступен', $exception);
        } catch (SearchFailedException $exception) {
            report($exception);
            throw new HttpException(500, 'Ошибка поиска', $exception);
        }

        $result = $this->facets->present($raw, $criteria);
        $tookMs = is_numeric($raw['took'] ?? null) ? (int) $raw['took'] : 0;

        try {
            $this->cache->put($cacheKey, $result, $this->cacheTtlSeconds);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->publish($criteria, $result['meta']['total'], $tookMs);

        return $result;
    }

    private function publish(SearchCriteriaData $criteria, int $total, int $tookMs): void
    {
        $this->events->performed($criteria->query, $criteria->filters(), $total, $tookMs);
    }
}
