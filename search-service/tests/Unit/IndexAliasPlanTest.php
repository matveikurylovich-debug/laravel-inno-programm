<?php

declare(strict_types=1);

use App\Services\Search\IndexAliasPlan;

it('replaces a concrete index with an alias on first switch', function (): void {
    $actions = IndexAliasPlan::actions('products', 'products_20261125', false, [], true);

    expect($actions[0]['add']['index'])->toBe('products_20261125')
        ->and($actions[0]['add']['alias'])->toBe('products')
        ->and($actions[0]['add']['is_write_index'])->toBeTrue()
        ->and($actions[1]['remove_index']['index'])->toBe('products');
});

it('removes previous concrete indexes and keeps the new one during rebuild', function (): void {
    $actions = IndexAliasPlan::actions('products', 'products_new', true, ['products_old', 'products_new'], false);

    expect($actions)->toHaveCount(2)
        ->and($actions[0]['add']['index'])->toBe('products_new')
        ->and($actions[1]['remove_index']['index'])->toBe('products_old');
});
