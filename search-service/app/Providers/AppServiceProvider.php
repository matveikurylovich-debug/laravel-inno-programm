<?php

namespace App\Providers;

use App\Contracts\ProductIndex;
use App\Services\ElasticsearchProductIndex;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function (): Client {
            return ClientBuilder::create()
                ->setHosts(config('elasticsearch.hosts'))
                ->build();
        });

        $this->app->singleton(ProductIndex::class, ElasticsearchProductIndex::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
