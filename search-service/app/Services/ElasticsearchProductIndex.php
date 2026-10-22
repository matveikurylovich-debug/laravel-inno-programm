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
                    'max_ngram_diff' => 12,
                    'analysis' => [
                        'filter' => [
                            'product_stemmer' => [
                                'type' => 'stemmer',
                                'language' => 'russian',
                            ],
                            'product_synonyms' => [
                                'type' => 'synonym_graph',
                                'synonyms' => $this->synonyms(),
                            ],
                            'product_edge' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 3,
                                'max_gram' => 15,
                            ],
                        ],
                        'analyzer' => [
                            'product_text' => [
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'product_stemmer'],
                            ],
                            'product_synonyms' => [
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'product_synonyms', 'product_stemmer'],
                            ],
                            'product_prefix' => [
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'product_edge'],
                            ],
                            'product_prefix_search' => [
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase'],
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
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                                'syn' => [
                                    'type' => 'text',
                                    'analyzer' => 'product_text',
                                    'search_analyzer' => 'product_synonyms',
                                ],
                                'prefix' => [
                                    'type' => 'text',
                                    'analyzer' => 'product_prefix',
                                    'search_analyzer' => 'product_prefix_search',
                                ],
                            ],
                        ],
                        'description' => [
                            'type' => 'text',
                            'analyzer' => 'product_text',
                            'fields' => [
                                'syn' => [
                                    'type' => 'text',
                                    'analyzer' => 'product_text',
                                    'search_analyzer' => 'product_synonyms',
                                ],
                            ],
                        ],
                        'price' => ['type' => 'scaled_float', 'scaling_factor' => 100],
                        'is_in_stock' => ['type' => 'boolean'],
                        'category_id' => ['type' => 'integer'],
                        'category_name' => [
                            'type' => 'keyword',
                            'fields' => [
                                'text' => ['type' => 'text', 'analyzer' => 'product_text'],
                                'syn' => [
                                    'type' => 'text',
                                    'analyzer' => 'product_text',
                                    'search_analyzer' => 'product_synonyms',
                                ],
                            ],
                        ],
                        'attributes' => [
                            'type' => 'nested',
                            'properties' => [
                                'key' => ['type' => 'keyword'],
                                'value' => [
                                    'type' => 'keyword',
                                    'fields' => [
                                        'text' => ['type' => 'text', 'analyzer' => 'product_text'],
                                        'syn' => [
                                            'type' => 'text',
                                            'analyzer' => 'product_text',
                                            'search_analyzer' => 'product_synonyms',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'updated_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Слова, которые покупатель считает одним и тем же.
     * Стоят только в анализаторе поиска: с fuzziness Elasticsearch их не совмещает.
     *
     * @return list<string>
     */
    private function synonyms(): array
    {
        return [
            'диван, софа',
            'сковорода, сковородка',
            'смартфон, телефон',
            'ноутбук, лэптоп',
            'наушники, гарнитура',
            'колонка, динамик',
            'кроссовки, кеды',
            'чехол, кейс',
            'зарядка, зарядное',
            'рюкзак, ранец',
            'палатка, шатер, шатёр',
            'футболка, майка',
            'торшер, лампа',
            'матрас, матрац',
            'чайник, электрочайник',
            'кофеварка, кофемашина',
        ];
    }
}
