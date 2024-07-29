<?php

namespace Elalecs\LaravelDocumenter;

use Illuminate\Support\ServiceProvider;
use Elalecs\LaravelDocumenter\Commands\GenerateDocumentation;
use Elalecs\LaravelDocumenter\Generators\ModelDocumenter;
use Elalecs\LaravelDocumenter\Generators\FilamentResourceDocumenter;
use Elalecs\LaravelDocumenter\Generators\ApiControllerDocumenter;
use Elalecs\LaravelDocumenter\Generators\JobDocumenter;
use Elalecs\LaravelDocumenter\Generators\EventDocumenter;
use Elalecs\LaravelDocumenter\Generators\MiddlewareDocumenter;
use Elalecs\LaravelDocumenter\Generators\RuleDocumenter;

/**
 * Laravel Documenter Service Provider
 *
 * This service provider bootstraps the Laravel Documenter package,
 * registering its config, commands, and services.
 */
class LaravelDocumenterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
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
     *
     * @return void
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
     *
     * This method binds each generator class to the service container
     * with a unique key for easy retrieval.
     *
     * @return void
     */
    protected function registerGenerators()
    {
        $generators = [
            'model' => ModelDocumenter::class,
            'filament-resource' => FilamentResourceDocumenter::class,
            'api-controller' => ApiControllerDocumenter::class,
            'job' => JobDocumenter::class,
            'event' => EventDocumenter::class,
            'middleware' => MiddlewareDocumenter::class,
            'rule' => RuleDocumenter::class,
        ];

        foreach ($generators as $key => $class) {
            $this->app->bind("documenter.$key", function ($app) use ($class) {
                return new $class($app['config']);
            });
        }
    }
}