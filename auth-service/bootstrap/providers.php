<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Ecommerce\PlatformKit\Laravel\PlatformKitServiceProvider;

return [
    PlatformKitServiceProvider::class,
    AppServiceProvider::class,
];
