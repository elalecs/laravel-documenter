<?php

/**
 * This file contains the LaravelDocumenter class which is responsible for generating
 * comprehensive documentation for a Laravel application.
 */

namespace Elalecs\LaravelDocumenter;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;

/**
 * Class LaravelDocumenter
 * 
 * This class is responsible for generating and managing various types of documentation
 * for a Laravel application, including general, model, API, and Filament documentation.
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
     * @param  \Illuminate\Contracts\Foundation\Application  $app The Laravel application instance
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app['config']['laravel-documenter'];
    }

    /**
     * Generate the documentation.
     *
     * This method orchestrates the generation of all types of documentation
     * and creates both individual files and a main contributing file.
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

        $this->generateIndividualFiles($documentation);
        $this->generateMainContributingFile($documentation);
    }

    /**
     * Generate individual documentation files.
     *
     * @param array $documentation An associative array of documentation types and their content
     * @return void
     */
    protected function generateIndividualFiles($documentation)
    {
        foreach ($documentation as $type => $content) {
            $outputPath = config("laravel-documenter.output_path.{$type}");
            File::put($outputPath, $content);
        }
    }

    /**
     * Generate the main contributing file.
     *
     * @param array $documentation An associative array of documentation types and their content
     * @return void
     */
    protected function generateMainContributingFile($documentation)
    {
        $content = View::file($this->getStubPath('contributing'), [
            'projectName' => config('app.name'),
            'documenters' => array_keys($documentation),
        ])->render();

        File::put(config('laravel-documenter.output_path.main'), $content);
    }

    /**
     * Generate the general documentation.
     *
     * @return string The generated general documentation content
     */
    public function generateGeneralDocumentation()
    {
        return $this->app->make('documenter.general')->generate();
    }

    /**
     * Generate the model documentation.
     *
     * @return string The generated model documentation content
     */
    public function generateModelDocumentation()
    {
        return $this->app->make('documenter.model')->generate();
    }

    /**
     * Generate the API documentation.
     *
     * @return string The generated API documentation content
     */
    public function generateApiDocumentation()
    {
        return $this->app->make('documenter.api')->generate();
    }

    /**
     * Generate the Filament documentation.
     *
     * @return string The generated Filament documentation content
     */
    public function generateFilamentDocumentation()
    {
        return $this->app->make('documenter.filament')->generate();
    }

    /**
     * Generate the contributing file.
     *
     * @param  array  $documentation An associative array of documentation types and their content
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
     * @param  array  $documentation An associative array of documentation types and their content
     * @return string The formatted documentation string
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
     * @param  string  $stub The name of the stub file
     * @return string The full path to the stub file
     */
    protected function getStubPath($stub)
    {
        return $this->config['stubs_path'] . "/{$stub}.blade.php";
    }
}