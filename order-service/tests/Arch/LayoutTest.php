<?php

declare(strict_types=1);

arch('app and tests declare strict types')
    ->expect(['App', 'Tests'])
    ->toUseStrictTypes();

arch('kafka stays in messaging and console')
    ->expect('Junges\Kafka')
    ->toOnlyBeUsedIn(['App\Messaging', 'App\Console'])
    ->ignoring('Ecommerce\PlatformKit');

arch('stripe sdk stays in the integration and providers')
    ->expect('Stripe')
    ->toOnlyBeUsedIn(['App\Integrations\Stripe', 'App\Providers'])
    ->ignoring('Ecommerce\PlatformKit');

arch('the service does not use the amqp client')
    ->expect('PhpAmqpLib')
    ->not->toBeUsed()
    ->ignoring('Ecommerce\PlatformKit');

arch('controllers use the Controller suffix')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('commands use the Command suffix')
    ->expect('App\Console\Commands')
    ->toHaveSuffix('Command');

arch('form requests use the Request suffix')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

arch('api resources use the Resource suffix')
    ->expect('App\Http\Resources')
    ->toHaveSuffix('Resource');

arch('consumers use the Consumer suffix')
    ->expect('App\Messaging\Consumers')
    ->toHaveSuffix('Consumer');

arch('producers use the Producer suffix')
    ->expect('App\Messaging\Producers')
    ->toHaveSuffix('Producer');

arch('policies use the Policy suffix')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('providers use the ServiceProvider suffix')
    ->expect('App\Providers')
    ->toHaveSuffix('ServiceProvider');

arch('data objects use the Data suffix')
    ->expect('App\Data')
    ->toHaveSuffix('Data');

arch('contracts omit the Interface suffix')
    ->expect('App\Contracts')
    ->not->toHaveSuffix('Interface');

arch('classes omit the Middleware suffix')
    ->expect('App')
    ->not->toHaveSuffix('Middleware');

arch('services stay in a domain namespace')
    ->expect('App\Services')
    ->not->toBeClasses()
    ->ignoring([
        'App\Services\Orders',
        'App\Services\Outbox',
        'App\Services\Payments',
    ]);

it('declares strict types in every app and test file', function (): void {
    $missing = [];

    foreach ([dirname(__DIR__, 2).'/app', dirname(__DIR__)] as $directory) {
        if (! is_dir($directory)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (! preg_match('/^<\?php\s*(\/\*[\s\S]*?\*\/|\/\/[^\r\n]*(?:\r?\n|$)|\s)*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m', $contents)) {
                $missing[] = $file->getPathname();
            }
        }
    }

    expect($missing)->toBe([]);
});

it('keeps order command signatures under the order prefix', function (): void {
    $files = glob(dirname(__DIR__, 2).'/app/Console/Commands/*Command.php');

    expect($files)->not->toBeEmpty();

    foreach ($files ?: [] as $file) {
        expect((string) file_get_contents($file))->toContain("= 'order:");
    }
});
