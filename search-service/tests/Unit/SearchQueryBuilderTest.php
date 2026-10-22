<?php

namespace Tests\Unit;

use App\Search\SearchQueryBuilder;
use Tests\TestCase;

class SearchQueryBuilderTest extends TestCase
{
    public function test_builds_full_text_filters_and_facets(): void
    {
        $query = (new SearchQueryBuilder)->build([
            'q' => 'iphone',
            'category_id' => 5,
            'price_min' => 100,
            'price_max' => 900,
            'in_stock' => true,
            'page' => 2,
            'per_page' => 10,
            'sort' => 'price_asc',
        ]);

        $this->assertSame(10, $query['from']);
        $this->assertSame(10, $query['size']);
        $this->assertSame([['price' => 'asc']], $query['sort']);
        $text = $query['query']['bool']['must'][0]['bool'];
        $this->assertSame(1, $text['minimum_should_match']);
        $this->assertSame('iphone', $text['should'][0]['match']['name']['query']);
        $this->assertSame(5, $text['should'][0]['match']['name']['boost']);
        $this->assertSame(
            ['name^3', 'description', 'category_name.text^2'],
            $text['should'][1]['multi_match']['fields'],
        );
        $this->assertSame(1, $text['should'][1]['multi_match']['fuzziness']);
        $this->assertSame(
            ['name.syn^3', 'description.syn', 'category_name.syn^2'],
            $text['should'][2]['multi_match']['fields'],
        );
        $this->assertArrayHasKey('name.prefix', $text['should'][3]['match']);
        $this->assertSame('attributes', $text['should'][4]['nested']['path']);
        $this->assertSame(
            ['term' => ['category_id' => 5]],
            $query['query']['bool']['filter'][0],
        );
        $this->assertSame(
            ['range' => ['price' => ['gte' => 100.0, 'lte' => 900.0]]],
            $query['query']['bool']['filter'][1],
        );
        $this->assertArrayHasKey('categories', $query['aggs']);
        $this->assertArrayHasKey('in_stock_count', $query['aggs']);
        $this->assertArrayHasKey('price_range', $query['aggs']);
        $this->assertArrayHasKey('attributes', $query['aggs']);
    }
}
