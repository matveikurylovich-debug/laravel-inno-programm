<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use Ecommerce\PlatformKit\Laravel\PlatformKitServiceProvider;

return [
    PlatformKitServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
];
