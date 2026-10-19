<?php

namespace App\Providers;

use App\Contracts\AuthService;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(JwtService::class);
        $this->app->singleton(AuthService::class, fn ($app) => $app->make(JwtService::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Vite::prefetch(concurrency: 3);

        Gate::define('access-admin', fn (User $user) => $user->hasRole('admin'));
    }
}
