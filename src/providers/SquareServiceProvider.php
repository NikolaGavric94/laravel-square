<?php

namespace Nikolag\Square\Providers;

use Illuminate\Support\ServiceProvider;
use Nikolag\Square\Contracts\SquareServiceContract;
use Nikolag\Square\SquareConfig;
use Nikolag\Square\SquareService;

class SquareServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/nikolag.php' => config_path('nikolag.php'),
        ], 'nikolag_config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //Config
        $this->mergeConfigFrom(__DIR__.'/../config/nikolag.php', 'nikolag');

        $this->app->singleton(SquareConfig::class, function ($app) {
            return new SquareConfig();
        });

        //DI
        $this->app->bind(
            SquareServiceContract::class,
            SquareService::class
        );

        //Facades
        $this->app->alias(SquareService::class, 'square');
    }
}
