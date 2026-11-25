<?php

namespace App\Providers;

use App\Contracts\ProductIndex;
use App\Services\ElasticsearchProductIndex;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $secret = getenv('JWT_ACCESS_SECRET');
        $command = $_SERVER['argv'][1] ?? '';
        $tooling = ['package:discover', 'config:clear', 'config:cache', 'key:generate', 'about', 'list', 'test'];
        $argv0 = $_SERVER['argv'][0] ?? '';
        if (($secret === false || $secret === '') && ! in_array($command, $tooling, true) && ! str_contains($argv0, 'phpstan')) {
            throw new RuntimeException('JWT-секрет пуст, запустите ./start.sh');
        }

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
