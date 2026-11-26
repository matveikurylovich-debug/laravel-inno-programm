<?php

declare(strict_types=1);

use App\Console\Commands\DlqReplayCommand;
use App\Integrations\Elasticsearch\ElasticsearchProductIndex;
use App\Services\Search\ProductDocument;
use Elastic\Elasticsearch\ClientBuilder;

it('maps product event payload into normalized elasticsearch document', function (): void {
    $doc = (new ProductDocument)->fromProductEvent([
        'product_id' => 42,
        'store_id' => '3',
        'name' => 'Смартфон',
        'description' => 'OLED',
        'price' => '49990.50',
        'available' => 2,
        'category_id' => 8,
        'category_path' => ['1', 8],
        'category_name' => 'Электроника',
        'attributes' => ['brand' => 'Acme'],
    ]);

    expect($doc['id'])->toBe('42')
        ->and($doc['store_id'])->toBe(3)
        ->and($doc['price'])->toBe(49990.5)
        ->and($doc['is_in_stock'])->toBeTrue()
        ->and($doc['category_path'])->toBe([1, 8])
        ->and($doc['attributes'])->toBe([['key' => 'brand', 'value' => 'Acme']]);
});

it('loads index definition from resources/elasticsearch/products.json', function (): void {
    $client = ClientBuilder::create()->setHosts(['http://localhost:9200'])->build();
    $index = new ElasticsearchProductIndex($client, 'products_unit');
    $definition = $index->indexDefinition();

    expect($index->indexName())->toBe('products_unit')
        ->and($definition)->toHaveKeys(['settings', 'mappings'])
        ->and($definition['settings']['analysis']['filter']['product_stemmer']['language'])->toBe('russian')
        ->and($definition['settings']['analysis']['filter']['product_synonyms']['synonyms'])->toContain('смартфон, телефон')
        ->and($definition['mappings']['properties'])->toHaveKeys(['id', 'name', 'description', 'price', 'category_path', 'attributes']);
});

it('extracts source topic from dlq topic name', function (): void {
    expect(DlqReplayCommand::sourceTopic('catalog.events.dlq.search-service'))
        ->toBe('catalog.events');
});
