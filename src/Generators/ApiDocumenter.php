<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionParameter;

class ApiDocumenter
{
    protected $config;
    protected $documentation = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function generate()
    {
        $routeFiles = $this->getRouteFiles();

        foreach ($routeFiles as $file) {
            $this->documentRouteFile($file);
        }

        return $this->formatDocumentation();
    }

    protected function getRouteFiles()
    {
        $path = $this->config['path'] ?? base_path('routes');
        $files = $this->config['files'] ?? ['api.php'];

        return collect($files)->map(function ($file) use ($path) {
            return $path . '/' . $file;
        })->filter(function ($file) {
            return File::exists($file);
        });
    }

    protected function documentRouteFile($file)
    {
        $fileName = basename($file);
        $this->documentation[$fileName] = [];

        // Load the routes
        require $file;

        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            if ($this->isApiRoute($route)) {
                $this->documentRoute($fileName, $route);
            }
        }
    }

    protected function isApiRoute($route)
    {
        // You might want to adjust this logic based on your API route naming conventions
        return Str::startsWith($route->uri(), 'api/');
    }

    protected function documentRoute($fileName, $route)
    {
        $methods = $route->methods();
        $uri = $route->uri();
        $action = $route->getAction();

        $handlerClass = $action['controller'] ?? null;
        if ($handlerClass) {
            list($class, $method) = explode('@', $handlerClass);
        } else {
            $class = null;
            $method = null;
        }

        $this->documentation[$fileName][] = [
            'methods' => $methods,
            'uri' => $uri,
            'handlerClass' => $class,
            'handlerMethod' => $method,
            'middleware' => $this->getMiddleware($route),
            'parameters' => $this->getParameters($class, $method),
            'description' => $this->getMethodDescription($class, $method),
        ];
    }

    protected function getMiddleware($route)
    {
        return collect($route->gatherMiddleware())
            ->map(function ($middleware) {
                return is_string($middleware) ? $middleware : get_class($middleware);
            })
            ->values()
            ->toArray();
    }

    protected function getParameters($class, $method)
    {
        if (!$class || !$method) {
            return [];
        }

        try {
            $reflection = new ReflectionMethod($class, $method);
            return collect($reflection->getParameters())->map(function (ReflectionParameter $param) {
                return [
                    'name' => $param->getName(),
                    'type' => $param->getType() ? $param->getType()->getName() : 'mixed',
                ];
            })->toArray();
        } catch (\ReflectionException $e) {
            return [];
        }
    }

    protected function getMethodDescription($class, $method)
    {
        if (!$class || !$method) {
            return 'No description available.';
        }

        try {
            $reflection = new ReflectionMethod($class, $method);
            $docComment = $reflection->getDocComment();
            if ($docComment) {
                preg_match('/@description\s+(.*)\n/s', $docComment, $matches);
                return $matches[1] ?? 'No description available.';
            }
        } catch (\ReflectionException $e) {
            // Do nothing
        }

        return 'No description available.';
    }

    protected function formatDocumentation()
    {
        $output = '';

        foreach ($this->documentation as $fileName => $routes) {
            $output .= "## $fileName\n\n";

            foreach ($routes as $route) {
                $methods = implode(', ', $route['methods']);
                $output .= "### {$methods} {$route['uri']}\n\n";
                
                if ($route['handlerClass'] && $route['handlerMethod']) {
                    $output .= "**Handler:** {$route['handlerClass']}@{$route['handlerMethod']}\n\n";
                }

                $output .= "{$route['description']}\n\n";

                if (!empty($route['parameters'])) {
                    $output .= "**Parameters:**\n";
                    foreach ($route['parameters'] as $param) {
                        $output .= "- {$param['name']} ({$param['type']})\n";
                    }
                    $output .= "\n";
                }

                if (!empty($route['middleware'])) {
                    $output .= "**Middleware:**\n";
                    foreach ($route['middleware'] as $middleware) {
                        $output .= "- $middleware\n";
                    }
                    $output .= "\n";
                }

                $output .= "\n";
            }
        }

        return $output;
    }
}