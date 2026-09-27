<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Services\JwtService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем как Singleton: создаётся один раз на запрос
        $this->app->singleton(JwtService::class, function () {
            return new JwtService();
        });
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
