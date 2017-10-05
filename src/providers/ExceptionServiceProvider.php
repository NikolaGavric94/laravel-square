<?php

namespace Nikolag\Square\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Nikolag\Square\ExceptionHandler;

class ExceptionServiceProvider extends ServiceProvider
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
        $this->app->bind(
            ExceptionHandlerContract::class,
            ExceptionHandler::class
        );
    }
}
