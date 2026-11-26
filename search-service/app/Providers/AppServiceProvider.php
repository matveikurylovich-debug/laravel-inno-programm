<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\ProductIndex;
use App\Integrations\Elasticsearch\ElasticsearchProductIndex;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(function (Container $app): Client {
            $config = $app->make(ConfigRepository::class);
            $rawHosts = $config->get('elasticsearch.hosts', ['http://elasticsearch:9200']);
            $hosts = is_array($rawHosts)
                ? array_values(array_map(static fn (mixed $host): string => is_scalar($host) ? (string) $host : '', $rawHosts))
                : ['http://elasticsearch:9200'];

            $timeout = $config->get('elasticsearch.timeout', 2.0);
            $connectTimeout = $config->get('elasticsearch.connect_timeout', 1.0);

            return ClientBuilder::create()
                ->setHosts($hosts)
                ->setHttpClientOptions([
                    'timeout' => is_numeric($timeout) ? (float) $timeout : 2.0,
                    'connect_timeout' => is_numeric($connectTimeout) ? (float) $connectTimeout : 1.0,
                ])
                ->build();
        });

        $this->app->singleton(ProductIndex::class, ElasticsearchProductIndex::class);
    }

    public function boot(): void
    {
        //
    }
}
