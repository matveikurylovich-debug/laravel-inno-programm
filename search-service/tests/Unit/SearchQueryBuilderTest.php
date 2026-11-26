<?php

declare(strict_types=1);

use App\Data\SearchCriteriaData;
use App\Enums\SearchSort;
use App\Services\Search\SearchQueryBuilder;

it('builds full text match, filters, price range, sort, and aggregations', function (): void {
    $criteria = SearchCriteriaData::fromArray([
        'q' => 'iphone',
        'category_id' => 5,
        'price_min' => 100,
        'price_max' => 900,
        'in_stock' => true,
        'page' => 2,
        'per_page' => 10,
        'sort' => SearchSort::PriceAsc->value,
    ]);

    $query = (new SearchQueryBuilder)->build($criteria);

    expect($query['timeout'])->toBe('2s')
        ->and($query['from'])->toBe(10)
        ->and($query['size'])->toBe(10)
        ->and($query['sort'])->toBe([['price' => 'asc']]);

    /** @var array<string, mixed> $boolQuery */
    $boolQuery = $query['query']['bool'];
    /** @var array<string, mixed> $text */
    $text = $boolQuery['must'][0]['bool'];

    expect($text['minimum_should_match'])->toBe(1)
        ->and($text['should'][0]['match']['name']['query'])->toBe('iphone')
        ->and($text['should'][0]['match']['name']['boost'])->toBe(5)
        ->and($text['should'][1]['multi_match']['fields'])->toBe(['name^3', 'description', 'category_name.text^2'])
        ->and($text['should'][1]['multi_match']['fuzziness'])->toBe('AUTO')
        ->and($text['should'][2]['multi_match']['fields'])->toBe(['name.syn^3', 'description.syn', 'category_name.syn^2'])
        ->and($text['should'][3]['match'])->toHaveKey('name.prefix')
        ->and($text['should'][4]['nested']['path'])->toBe('attributes')
        ->and($boolQuery['filter'][0])->toBe(['term' => ['category_path' => 5]])
        ->and($boolQuery['filter'][1])->toBe(['range' => ['price' => ['gte' => 100.0, 'lte' => 900.0]]])
        ->and($boolQuery['filter'][2])->toBe(['term' => ['is_in_stock' => true]])
        ->and($query['aggs'])->toHaveKeys(['categories', 'in_stock_count', 'price_range', 'attributes']);
});

it('returns no hits for queries that are not product text', function (): void {
    foreach (['<script>alert(1)</script>', '"\'; DROP', '"; OR 1=1'] as $query) {
        $built = (new SearchQueryBuilder)->build(['q' => $query]);

        /** @var array<string, mixed> $boolQuery */
        $boolQuery = $built['query']['bool'];

        expect($boolQuery['must'][0])->toHaveKey('match_none');
    }
});

it('falls back to match_all when query string is blank', function (): void {
    $query = (new SearchQueryBuilder)->build(['q' => '   ']);

    /** @var array<string, mixed> $boolQuery */
    $boolQuery = $query['query']['bool'];

    expect($boolQuery['must'][0])->toHaveKey('match_all')
        ->and($boolQuery['filter'])->toBe([])
        ->and($query['sort'])->toBe([['_score' => 'desc']]);
});
