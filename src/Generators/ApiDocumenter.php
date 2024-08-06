<?php

/**
 * This file contains the ApiDocumenter class which is responsible for generating API documentation
 * based on the routes defined in a Laravel application.
 */

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class ApiDocumenter
 *
 * This class extends BasePhpParserDocumenter and provides functionality to generate
 * API documentation by parsing and formatting the application's routes.
 *
 * @package Elalecs\LaravelDocumenter\Generators
 */
class ApiDocumenter extends BasePhpParserDocumenter
{
    /**
     * Configuration array for the API documenter.
     *
     * @var array
     */
    protected $config;

    /**
     * Path to the stub file used for generating documentation.
     *
     * @var string
     */
    protected $stubPath;

    /**
     * ApiDocumenter constructor.
     *
     * @param array $config Configuration array for the API documenter
     */
    public function __construct($config)
    {
        parent::__construct($config);
        $this->config = $config;
        $this->setStubPath();
        $this->log('info', 'ApiDocumenter initialized');
    }

    /**
     * Set the stub path for the API documenter
     *
     * @throws \RuntimeException If the stub file is not found
     * @return void
     */
    protected function setStubPath()
    {
        $stubsPath = $this->config['stubs_path'] ?? __DIR__.'/../Stubs';
        $this->stubPath = $stubsPath . "/api-documenter.blade.php";
        
        if (!file_exists($this->stubPath)) {
            throw new \RuntimeException("API documenter stub not found at {$this->stubPath}");
        }
        $this->log('info', 'Stub path set');
    }

    /**
     * Generate the API documentation
     *
     * This method calls the Laravel route:list command, formats the output,
     * and renders it using a blade template.
     *
     * @return string The rendered API documentation
     */
    public function generate()
    {
        $this->log('info', 'Generating API documentation');
        $output = new BufferedOutput();
        Artisan::call('route:list', [
            '--json' => true,
            '--path' => 'api',
        ], $output);

        $routes = json_decode($output->fetch(), true);

        $tableData = $this->formatRoutesAsTable($routes);

        return View::file($this->stubPath, [
            'apiGroupName' => 'API Routes',
            'tableHeaders' => ['Method', 'URI', 'Name', 'Action', 'Middleware'],
            'tableRows' => $tableData,
        ])->render();
    }

    /**
     * Format the routes as a table
     *
     * This method takes the raw route data and formats it into a table structure.
     *
     * @param array $routes The raw route data
     * @return array The formatted table data
     */
    protected function formatRoutesAsTable($routes)
    {
        $this->log('info', 'Formatting routes as table');
        $tableData = [];

        foreach ($routes as $route) {
            $tableData[] = [
                'method' => $this->formatMethod($route['method']),
                'uri' => $route['uri'],
                'name' => $route['name'] ?? '',
                'action' => $route['action'],
                'middleware' => $this->formatMiddleware($route['middleware']),
            ];
        }

        return $tableData;
    }

    /**
     * Format the HTTP method
     *
     * This method ensures that the HTTP method is always returned as a string.
     *
     * @param string|array $method The HTTP method(s)
     * @return string The formatted HTTP method(s)
     */
    protected function formatMethod($method)
    {
        $this->log('info', 'Formatting HTTP method');
        if (is_array($method)) {
            return implode(',', $method);
        }
        return (string)$method;
    }

    /**
     * Format the middleware
     *
     * This method ensures that the middleware is always returned as a string.
     *
     * @param string|array $middleware The middleware(s)
     * @return string The formatted middleware(s)
     */
    protected function formatMiddleware($middleware)
    {
        $this->log('info', 'Formatting middleware');
        if (is_array($middleware)) {
            return implode(',', $middleware);
        }
        return (string)$middleware;
    }
}