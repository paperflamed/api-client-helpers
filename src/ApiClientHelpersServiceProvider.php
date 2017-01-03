<?php

namespace Wizz\ApiClientHelpers;

use Illuminate\Support\ServiceProvider;

class ApiClientHelpersServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/configs/api_configs.php' => config_path('api_configs.php'),
        ]);
        //
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        include __DIR__.'/routes.php';
        include __DIR__.'/ACHController.php';
        include __DIR__.'/Helpers/array.php';
        include __DIR__.'/Helpers/cookies.php';
        include __DIR__.'/Helpers/request.php';
        $this->loadViewsFrom(__DIR__.'/views', 'api-client-helpers');
        $this->mergeConfigFrom(__DIR__.'/configs/api_configs.php', 'api_configs');
        $this->app->make('Wizz\ApiClientHelpers\Token');
        $this->app->make('Wizz\ApiClientHelpers\ACHController');
        $this->app->make('Wizz\ApiClientHelpers\Middleware\BlockUrlsMiddleware');
        $this->app->make('Wizz\ApiClientHelpers\Middleware\BrowserVersionMiddleware');
    }
}
