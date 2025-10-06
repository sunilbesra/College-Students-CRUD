<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap for pagination styling
        Paginator::useBootstrap();
        
        // Bind a named cache store for CSV batch metadata so we can switch driver easily
        // Usage: app('csv.batch')->put(...)
        $this->app->singleton('csv.batch', function ($app) {
            // allow overriding via CSV_CACHE_DRIVER env var, fallback to default cache
            $driver = env('CSV_CACHE_DRIVER', config('cache.default'));
            try {
                return \Illuminate\Support\Facades\Cache::store($driver);
            } catch (\Throwable $e) {
                // fallback to default cache store
                return \Illuminate\Support\Facades\Cache::store();
            }
        });
    }
}
