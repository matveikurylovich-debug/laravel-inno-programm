<?php

declare(strict_types=1);

use App\Console\Commands\ConsumeAuthEventsCommand;
use App\Console\Commands\ConsumeOrderEventsCommand;
use App\Console\Commands\ConvertNotificationPayloadsCommand;
use App\Console\Commands\PruneNotificationLogsCommand;
use App\Console\Commands\RedactNotificationSecretsCommand;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Messaging\Consumers\AuthEventsConsumer;
use App\Messaging\Consumers\OrderEventsConsumer;
use Ecommerce\PlatformKit\Laravel\ConsumeKafkaCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

arch('application code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('tests use strict types')
    ->expect('Tests')
    ->toUseStrictTypes();

arch('controllers are named as controllers')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('form requests are named as requests')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

arch('resources are named after the model')
    ->expect('App\Http\Resources')
    ->toHaveSuffix('Resource');

arch('data transfer objects are final readonly')
    ->expect('App\Data')
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveSuffix('Data');

arch('commands are named as commands')
    ->expect('App\Console\Commands')
    ->toHaveSuffix('Command');

arch('topic consumers live in the messaging namespace')
    ->expect('App\Messaging\Consumers')
    ->toHaveSuffix('Consumer');

arch('application code does not use the kafka namespace')
    ->expect('App')
    ->not->toUse('App\Kafka');

arch('kafka consume commands use the platform command')
    ->expect([
        ConsumeAuthEventsCommand::class,
        ConsumeOrderEventsCommand::class,
    ])
    ->toExtend(ConsumeKafkaCommand::class);

it('keeps notification classes inside the layout directories', function (): void {
    $app = dirname(__DIR__, 2).'/app';

    foreach (['Kafka', 'Search', 'Infrastructure', 'Actions', 'DTO', 'DTOs', 'Events'] as $directory) {
        expect(is_dir($app.'/'.$directory))->toBeFalse();
    }

    expect(glob($app.'/Services/*.php'))->toBe([])
        ->and(glob($app.'/Http/Controllers/Api/*.php'))->toBe([])
        ->and(glob($app.'/Http/Controllers/Api/V1/*Controller.php'))->not->toBe([]);

    $middleware = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($app));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), 'Middleware.php')) {
            $middleware[] = $file->getPathname();
        }
    }

    expect($middleware)->toBe([]);
});

it('declares strict types in every app and test file', function (): void {
    $roots = [
        dirname(__DIR__, 2).'/app',
        dirname(__DIR__, 2).'/tests',
    ];

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            expect($contents)->toBeString()->toContain('declare(strict_types=1);');
        }
    }
});

it('reads kafka topics groups and dead letter queues from config', function (): void {
    $forbidden = [
        'auth.events',
        'order.events',
        'auth.notifications',
        'notification-service.auth-events',
        'notification-service.order-events',
        '.dlq.',
    ];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());
        expect($contents)->toBeString();
        foreach ($forbidden as $literal) {
            expect($contents)->not->toContain($literal);
        }

        expect($contents)->not->toMatch('/config\s*\([^)\n]*,/');
    }
});

it('removes public broadcast leak and extracts openapi attributes out of controllers', function (): void {
    expect(class_exists('App\\Events\\NotificationDispatched'))->toBeFalse();

    $controllerFile = new ReflectionClass(NotificationController::class)->getFileName();
    $controllerContents = is_string($controllerFile) ? (string) file_get_contents($controllerFile) : '';

    expect(str_contains($controllerContents, 'OpenApi\\Attributes'))->toBeFalse()
        ->and(str_contains($controllerContents, '#[OA\\'))->toBeFalse();
});

it('registers versioned notification routes and commands', function (): void {
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    expect((new ConsumeAuthEventsCommand)->getName())->toBe('notification:consume-auth-events')
        ->and((new ConsumeOrderEventsCommand)->getName())->toBe('notification:consume-order-events')
        ->and((new RedactNotificationSecretsCommand)->getName())->toBe('notification:redact-secrets')
        ->and((new RedactNotificationSecretsCommand)->getAliases())->toContain('notifications:purge-secrets')
        ->and((new ConvertNotificationPayloadsCommand)->getName())->toBe('notifications:convert-payloads')
        ->and((new PruneNotificationLogsCommand)->getName())->toBe('notification:prune-logs');

    $names = collect(Route::getRoutes()->getRoutes())
        ->map(static fn ($route): ?string => $route->getName())
        ->filter()
        ->values()
        ->all();

    expect($names)->toContain('api.v1.notifications.index')
        ->and($names)->toContain('api.v1.notifications.show')
        ->and($names)->toContain('api.v1.notifications.replay');

    $commands = Artisan::all();
    expect($commands['notification:consume-auth-events'])->toBeInstanceOf(ConsumeKafkaCommand::class)
        ->and($commands['notification:consume-order-events'])->toBeInstanceOf(ConsumeKafkaCommand::class);

    expect(config('kafka.topics.auth_events'))->toBe('auth.events')
        ->and(config('kafka.topics.order_events'))->toBe('order.events')
        ->and(config('kafka.consumers.auth-events.group'))->toBe('notification-service.auth-events')
        ->and(config('kafka.consumers.auth-events.dlq'))->toBe('auth.events.dlq.notification-service')
        ->and(config('kafka.consumers.auth-events.topics'))->toBe(['auth_events'])
        ->and(config('kafka.consumers.order-events.group'))->toBe('notification-service.order-events')
        ->and(config('kafka.consumers.order-events.dlq'))->toBe('order.events.dlq.notification-service')
        ->and(config('kafka.consumers.order-events.topics'))->toBe(['order_events'])
        ->and(config('kafka.consumers.auth-events.handler'))->toBe(AuthEventsConsumer::class)
        ->and(config('kafka.consumers.order-events.handler'))->toBe(OrderEventsConsumer::class)
        ->and(config('kafka.consumer_group_id'))->toBeNull()
        ->and(config('kafka.order_consumer_group_id'))->toBeNull();
})->after(function (): void {
    restore_error_handler();
    restore_exception_handler();
});
