<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\View;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class ApiDocumenter
 * @package Elalecs\LaravelDocumenter\Generators
 */
class ApiDocumenter extends BasePhpParserDocumenter
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $stubPath;

    /**
     * ApiDocumenter constructor.
     * @param array $config
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
     * @return string
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
     * @param array $routes
     * @return array
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
     * @param string|array $method
     * @return string
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
     * @param string|array $middleware
     * @return string
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