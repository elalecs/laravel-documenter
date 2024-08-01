<?php

namespace Elalecs\LaravelDocumenter;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;

/**
 * Class LaravelDocumenter
 * 
 * This class is responsible for generating documentation for a Laravel application.
 * 
 * @package Elalecs\LaravelDocumenter
 */
class LaravelDocumenter
{
    /**
     * The Laravel application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The configuration for the LaravelDocumenter.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new LaravelDocumenter instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app['config']['laravel-documenter'];
    }

    /**
     * Generate the documentation.
     *
     * @return void
     */
    public function generate()
    {
        $documentation = [
            'general' => $this->generateGeneralDocumentation(),
            'model' => $this->generateModelDocumentation(),
            'api' => $this->generateApiDocumentation(),
            'filament' => $this->generateFilamentDocumentation(),
        ];

        $this->generateContributingFile($documentation);
    }

    /**
     * Generate the general documentation.
     *
     * @return string
     */
    public function generateGeneralDocumentation()
    {
        return $this->app->make('documenter.general')->generate();
    }

    /**
     * Generate the model documentation.
     *
     * @return string
     */
    public function generateModelDocumentation()
    {
        return $this->app->make('documenter.model')->generate();
    }

    /**
     * Generate the API documentation.
     *
     * @return string
     */
    public function generateApiDocumentation()
    {
        return $this->app->make('documenter.api')->generate();
    }

    /**
     * Generate the Filament documentation.
     *
     * @return string
     */
    public function generateFilamentDocumentation()
    {
        return $this->app->make('documenter.filament')->generate();
    }


    /**
     * Generate the contributing file.
     *
     * @param  array  $documentation
     * @return void
     */
    public function generateContributingFile($documentation)
    {
        $content = View::file($this->getStubPath('contributing'), [
            'projectName' => config('app.name'),
            'generatedDocumentation' => $this->formatDocumentation($documentation),
        ])->render();

        File::put($this->config['output_path'], $content);
    }

    /**
     * Format the documentation array into a string.
     *
     * @param  array  $documentation
     * @return string
     */
    protected function formatDocumentation($documentation)
    {
        $formattedDoc = '';
        foreach ($documentation as $type => $content) {
            $formattedDoc .= "## $type Documentation\n\n$content\n\n";
        }
        return $formattedDoc;
    }

    /**
     * Get the path to a stub file.
     *
     * @param  string  $stub
     * @return string
     */
    protected function getStubPath($stub)
    {
        return $this->config['stubs_path'] . "/{$stub}.blade.php";
    }
}