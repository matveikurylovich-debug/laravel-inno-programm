<?php

declare(strict_types=1);
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Providers\Filament\AdminPanelProvider;
use App\Services\Products\CatalogCacheService;
use Ecommerce\PlatformKit\Laravel\ConsumeKafkaCommand;
use Junges\Kafka\Contracts\Handler;

// preset laravel падает на контроллерах с нестандартными публичными методами (tree, updateStock)
// и на Filament-провайдере без суффикса ServiceProvider: ->ignoring() конкретных классов, см. STRUCT-10
arch()->preset()->laravel()->ignoring([
    CategoryController::class,
    ProductController::class,
    AdminPanelProvider::class,
    CatalogCacheService::class,
]);

arch('strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Kafka только в Messaging и консольных командах')
    ->expect('Junges\Kafka')
    ->toOnlyBeUsedIn(['App\Messaging', 'App\Console\Commands'])
    ->ignoring(ConsumeKafkaCommand::class);

arch('Elasticsearch только в Integrations')
    ->expect('Elastic\Elasticsearch')
    ->toOnlyBeUsedIn(['App\Integrations', 'App\Providers']);

arch('Stripe только в Integrations')
    ->expect('Stripe')
    ->toOnlyBeUsedIn('App\Integrations');

arch('RabbitMQ только через platform-kit')
    ->expect('PhpAmqpLib')
    ->not->toBeUsed();

arch('консьюмеры')
    ->expect('App\Messaging\Consumers')
    ->toHaveSuffix('Consumer')
    ->toImplement(Handler::class);

arch('продюсеры')->expect('App\Messaging\Producers')->toHaveSuffix('Producer');
arch('команды')->expect('App\Console\Commands')->toHaveSuffix('Command');
arch('requests')->expect('App\Http\Requests')->toHaveSuffix('Request');
arch('resources')->expect('App\Http\Resources')->toHaveSuffix('Resource');
arch('DTO')->expect('App\Data')->toHaveSuffix('Data')->toBeFinal()->toBeReadonly();
arch('события без суффикса')->expect('App\Events')->not->toHaveSuffix('Event');
arch('middleware без суффикса')->expect('App\Http\Middleware')->not->toHaveSuffix('Middleware');
arch('listener без транспорта')->expect('App\Listeners')->not->toHavePrefix('SendKafka');

arch('no classes in root Services')
    ->expect('App\Services')
    ->not->toBeClasses()
    ->ignoring([
        'App\Services\Products',
        'App\Services\Stock',
    ]);

it('has no PHP files directly in app/Services', function (): void {
    $files = glob(dirname(__DIR__, 2).'/app/Services/*.php') ?: [];

    expect($files)->toBeEmpty();
});
