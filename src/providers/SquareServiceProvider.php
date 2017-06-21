<?php

namespace Nikolag\Square\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nikolag\Square\SquareConfig;

class SquareServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->publishes([
        //     __DIR__.'/../database/migrations/' => database_path('migrations')
        // ], 'nikolag-migrations');
        //Schema::defaultStringLength(191);
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(SquareConfig::class, function($app) {
            return new SquareConfig();
        });

        $this->app->alias(SquareConfig::class, 'square');

        //$this->app->resolving(SquareConfig::class, function ($api, $app) {
            //Log::info('Nikolag\Square\Square Resolved!');
        //});
    }
}
