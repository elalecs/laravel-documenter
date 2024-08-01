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
 * Service Provider for the LaravelDocumenter package.
 * 
 * This Service Provider is responsible for registering and configuring the necessary components
 * for the LaravelDocumenter package to function.
 */
class LaravelDocumenterServiceProvider extends ServiceProvider
{
    /**
     * Boot method of the Service Provider.
     * 
     * Runs after all Service Providers have been registered.
     * Here, the configuration files are published and the commands are registered.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-documenter.php' => config_path('laravel-documenter.php'),
            ], 'config');

            $this->commands([
                GenerateDocumentation::class,
            ]);
        }
    }

    /**
     * Register method of the Service Provider.
     * 
     * Runs when the Service Provider is registered by Laravel.
     * Here, the package configuration is merged and the documentation generators are registered.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-documenter.php', 'laravel-documenter'
        );

        $this->app->singleton('laravel-documenter', function () {
            return new LaravelDocumenter;
        });

        $this->registerGenerators();
    }

    /**
     * Registers the documentation generators.
     * 
     * Each generator is registered in the Laravel service container with a specific key.
     * This allows easy access to them through the container.
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