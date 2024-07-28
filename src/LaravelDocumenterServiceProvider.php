<?php

namespace Elalecs\LaravelDocumenter;

use Illuminate\Support\ServiceProvider;
use Elalecs\LaravelDocumenter\Commands\GenerateDocumentation;

class LaravelDocumenterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filament-documenter.php' => config_path('filament-documenter.php'),
            ], 'config');

            $this->commands([
                GenerateDocumentation::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Merge the package configuration file
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-documenter.php', 'laravel-documenter'
        );

        // Register the main class to use with the facade
        $this->app->singleton('laravel-documenter', function () {
            return new LaravelDocumenter;
        });

        // Register generators
        $this->registerGenerators();
    }

    /**
     * Register all the document generators.
     */
    protected function registerGenerators()
    {
        $generators = [
            'model' => Generators\ModelDocumenter::class,
            'filament-resource' => Generators\FilamentResourceDocumenter::class,
            'api-controller' => Generators\ApiControllerDocumenter::class,
            'job' => Generators\JobDocumenter::class,
            'event' => Generators\EventDocumenter::class,
            'middleware' => Generators\MiddlewareDocumenter::class,
            'rule' => Generators\RuleDocumenter::class,
        ];

        foreach ($generators as $key => $class) {
            $this->app->bind("documenter.$key", function ($app) use ($class) {
                return new $class($app['config']);
            });
        }
    }
}