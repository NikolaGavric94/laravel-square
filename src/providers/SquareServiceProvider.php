<?php

namespace Nikolag\Square\Providers;

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

        //Exception Service Provider
        $this->app->bind('Nikolag\Square\Providers\ExceptionServiceProvder');

        $this->app->singleton(SquareConfig::class, function($app) {
            return new SquareConfig();
        });

        $this->app->alias(SquareConfig::class, 'square');
    }
}
