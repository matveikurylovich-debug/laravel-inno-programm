<?php

namespace Tests\Unit;

use App\Search\FacetParser;
use Tests\TestCase;

class FacetParserTest extends TestCase
{
    public function test_turns_elasticsearch_aggregations_into_api_facets(): void
    {
        $result = (new FacetParser)->present([
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
            ],
        ], ['page' => 1, 'per_page' => 20]);

        $this->assertSame('Phone', $result['data'][0]['name']);
        $this->assertSame(42, $result['meta']['total']);
        $this->assertSame([
            ['id' => 5, 'name' => 'Смартфоны', 'count' => 30],
        ], $result['facets']['categories']);
        $this->assertSame(['true' => 38, 'false' => 4], $result['facets']['in_stock']);
        $this->assertSame(['min' => 250, 'max' => 1200], $result['facets']['price']);
    }
}
