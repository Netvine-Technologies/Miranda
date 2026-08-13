<?php

namespace App\Providers;

use App\Contracts\SearchProvider;
use App\Services\SearchDiscovery\SearchProviderManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SearchProvider::class, function ($app) {
            return $app->make(SearchProviderManager::class)->resolve();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
