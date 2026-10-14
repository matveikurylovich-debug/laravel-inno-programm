<?php

namespace App\Services;

use App\Contracts\ProductIndex;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class ElasticsearchProductIndex implements ProductIndex
{
    public function __construct(private readonly Client $client) {}

    public function indexName(): string
    {
        return (string) config('elasticsearch.index', 'products');
    }

    public function upsert(string $id, array $document): void
    {
        $this->client->index([
            'index' => $this->indexName(),
            'id' => $id,
            'body' => $document,
        ]);
    }

    public function delete(string $id): void
    {
        try {
            $this->client->delete([
                'index' => $this->indexName(),
                'id' => $id,
            ]);
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }
    }

    public function updateInStock(string $id, bool $inStock): void
    {
        try {
            $this->client->update([
                'index' => $this->indexName(),
                'id' => $id,
                'body' => [
                    'doc' => ['is_in_stock' => $inStock],
                ],
            ]);
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }
    }

    public function renameCategory(int $categoryId, string $name): void
    {
        $this->client->updateByQuery([
            'index' => $this->indexName(),
            'conflicts' => 'proceed',
            'body' => [
                'query' => ['term' => ['category_id' => $categoryId]],
                'script' => [
                    'source' => 'ctx._source.category_name = params.name',
                    'params' => ['name' => $name],
                ],
            ],
        ]);
    }

    public function search(array $query): array
    {
        $response = $this->client->search([
            'index' => $this->indexName(),
            'body' => $query,
        ]);

        return $response->asArray();
    }

    public function setup(bool $force = false): void
    {
        $index = $this->indexName();
        $exists = $this->client->indices()->exists(['index' => $index])->asBool();

        if ($exists && ! $force) {
            return;
        }

        if ($exists) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $this->client->indices()->create([
            'index' => $index,
            'body' => [
                'settings' => [
                    'analysis' => [
                        'filter' => [
                            'product_stemmer' => [
                                'type' => 'stemmer',
                                'language' => 'russian',
                            ],
                        ],
                        'analyzer' => [
                            'product_text' => [
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'product_stemmer'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'store_id' => ['type' => 'integer'],
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'product_text',
                            'fields' => ['keyword' => ['type' => 'keyword']],
                        ],
                        'description' => ['type' => 'text', 'analyzer' => 'product_text'],
                        'price' => ['type' => 'scaled_float', 'scaling_factor' => 100],
                        'is_in_stock' => ['type' => 'boolean'],
                        'category_id' => ['type' => 'integer'],
                        'category_name' => ['type' => 'keyword'],
                        'attributes' => [
                            'type' => 'nested',
                            'properties' => [
                                'key' => ['type' => 'keyword'],
                                'value' => ['type' => 'keyword'],
                            ],
                        ],
                        'updated_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ]);
    }
}
