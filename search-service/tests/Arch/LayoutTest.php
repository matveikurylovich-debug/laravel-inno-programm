<?php

declare(strict_types=1);

arch('elasticsearch stays in integrations and providers')
    ->expect('App')
    ->not()->toUse([
        'Elastic\Elasticsearch',
        'Elastic\Transport',
    ])
    ->ignoring([
        'App\Integrations\Elasticsearch',
        'App\Providers',
    ]);

it('keeps classes out of the services root', function (): void {
    expect(glob(dirname(__DIR__, 2).'/app/Services/*.php') ?: [])->toBe([]);
});

it('does not keep removed root namespaces', function (): void {
    $app = dirname(__DIR__, 2).'/app';

    expect(is_dir($app.'/Kafka'))->toBeFalse();
    expect(is_dir($app.'/Search'))->toBeFalse();
    expect(is_dir($app.'/Services/Analytics'))->toBeFalse();
});
