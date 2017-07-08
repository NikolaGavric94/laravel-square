<?php

namespace Nikolag\Square\Providers;

use Illuminate\Support\ServiceProvider;
use Nikolag\Square\SquareConfig;

class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(
            'Illuminate\Contracts\Debug\ExceptionHandler',
            'Nikolag\Square\ExceptionHandler'
        );
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
