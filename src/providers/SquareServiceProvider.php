<?php

namespace Nikolag\Square\Providers;

use Illuminate\Support\Facades\Log;
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
            return new SquareConfig(config('services.square'));
        });

        $this->app->alias(SquareConfig::class, 'square');

        //$this->app->resolving(SquareConfig::class, function ($api, $app) {
            //Log::info('Nikolag\Square\Square Resolved!');
        //});
    }
}
