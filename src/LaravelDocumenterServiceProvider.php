<?php

namespace Elalecs\LaravelDocumenter;

use Illuminate\Support\ServiceProvider;
use Elalecs\LaravelDocumenter\Commands\GenerateDocumentation;
use Elalecs\LaravelDocumenter\Generators\GeneralDocumenter;
use Elalecs\LaravelDocumenter\Generators\ModelDocumenter;
use Elalecs\LaravelDocumenter\Generators\ApiDocumenter;
use Elalecs\LaravelDocumenter\Generators\FilamentDocumenter;

/**
 * Service provider for Laravel Documenter package.
 */
class LaravelDocumenterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-documenter.php' => config_path('laravel-documenter.php'),
            ], 'config');

            $this->publishes([
                __DIR__.'/Stubs' => resource_path('views/vendor/laravel-documenter'),
            ], 'stubs');

            $this->commands([
                GenerateDocumentation::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-documenter.php', 'laravel-documenter'
        );

        $this->app->singleton('laravel-documenter', function ($app) {
            return new LaravelDocumenter($app);
        });

        $this->registerDocumenters();
    }

    /**
     * Register the documenters.
     *
     * @return void
     */
    protected function registerDocumenters()
    {
        $this->app->bind('documenter.general', function ($app) {
            return new GeneralDocumenter($app['config']['laravel-documenter.documenters.general']);
        });

        $this->app->bind('documenter.model', function ($app) {
            return new ModelDocumenter($app['config']['laravel-documenter.documenters.model']);
        });

        $this->app->bind('documenter.api', function ($app) {
            return new ApiDocumenter($app['config']['laravel-documenter.documenters.api']);
        });

        $this->app->bind('documenter.filament', function ($app) {
            return new FilamentDocumenter($app['config']['laravel-documenter.documenters.filament']);
        });
    }
}