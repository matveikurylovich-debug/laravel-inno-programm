<?php

declare(strict_types=1);

use App\Services\Search\SearchResultPresenter;

it('transforms elasticsearch hits and aggregations into api facets', function (): void {
    $result = (new SearchResultPresenter)->present([
        'hits' => [
            'total' => ['value' => 42],
            'hits' => [
                ['_source' => ['id' => '7', 'name' => 'Phone']],
            ],
        ],
        'aggregations' => [
            'categories' => [
                'buckets' => [
                    [
                        'key' => 5,
                        'doc_count' => 30,
                        'name' => ['buckets' => [['key' => 'Смартфоны']]],
                    ],
                ],
            ],
            'in_stock_count' => [
                'buckets' => [
                    'true' => ['doc_count' => 38],
                    'false' => ['doc_count' => 4],
                ],
            ],
            'price_range' => ['min' => 250, 'max' => 1200],
            'attributes' => [
                'keys' => [
                    'buckets' => [
                        [
                            'key' => 'color',
                            'values' => [
                                'buckets' => [
                                    ['key' => 'black', 'doc_count' => 12],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ], ['page' => 1, 'per_page' => 20]);

    expect($result['data'][0]['name'])->toBe('Phone')
        ->and($result['meta'])->toBe(['total' => 42, 'page' => 1, 'per_page' => 20])
        ->and($result['facets']['categories'])->toBe([
            ['id' => 5, 'name' => 'Смартфоны', 'count' => 30],
        ])
        ->and($result['facets']['in_stock'])->toBe(['true' => 38, 'false' => 4])
        ->and($result['facets']['price'])->toBe(['min' => 250, 'max' => 1200])
        ->and($result['facets']['attributes'])->toBe([
            [
                'key' => 'color',
                'values' => [['value' => 'black', 'count' => 12]],
            ],
        ]);
});
