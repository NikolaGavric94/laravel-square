<?php

namespace Nikolag\Square\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Nikolag\Square\Square;

class SquareServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Square::class, function($app) {
            return new Square(config('services.square'));
        });

        $this->app->alias(Square::class, 'square');

        $this->app->resolving(Square::class, function ($api, $app) {
            Log::info('Nikolag\Square\Square Resolved!');
        });
    }
}
