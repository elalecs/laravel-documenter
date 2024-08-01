<?php

namespace Elalecs\LaravelDocumenter;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;

class LaravelDocumenter
{
    protected $app;
    protected $config;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app['config'];
    }

    public function generateModelDocumentation()
    {
        return $this->app->make('documenter.model')->generate();
    }

    public function generateFilamentResourceDocumentation()
    {
        return $this->app->make('documenter.filament-resource')->generate();
    }

    public function generateApiControllerDocumentation()
    {
        return $this->app->make('documenter.api-controller')->generate();
    }

    public function generateJobDocumentation()
    {
        return $this->app->make('documenter.job')->generate();
    }

    public function generateEventDocumentation()
    {
        return $this->app->make('documenter.event')->generate();
    }

    public function generateMiddlewareDocumentation()
    {
        return $this->app->make('documenter.middleware')->generate();
    }

    public function generateRuleDocumentation()
    {
        return $this->app->make('documenter.rule')->generate();
    }

    public function generateAllDocumentation()
    {
        $documentation = '';
        $generators = [
            'model',
            'filament-resource',
            'api-controller',
            'job',
            'event',
            'middleware',
            'rule'
        ];

        foreach ($generators as $generator) {
            $method = 'generate' . str_replace('-', '', ucwords($generator, '-')) . 'Documentation';
            $documentation .= $this->$method();
        }

        return $documentation;
    }
}
