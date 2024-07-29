<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

/**
 * @description Class for documenting Laravel API controllers.
 */
class ApiControllerDocumenter
{
    protected $config;
    protected $stubPath;

    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/api-controller.stub';
    }

    /**
     * @description Generates documentation for all API controllers.
     * @return string Generated documentation
     */
    public function generate()
    {
        $controllers = $this->getApiControllers();
        $documentation = '';

        foreach ($controllers as $controller) {
            $documentation .= $this->documentController($controller);
        }

        return $documentation;
    }

    /**
     * @description Gets the list of API controllers.
     * @return array List of controller class names
     */
    protected function getApiControllers()
    {
        $controllerPath = app_path('Http/Controllers/Api');
        $files = File::allFiles($controllerPath);

        return collect($files)->map(function ($file) {
            return 'App\\Http\\Controllers\\Api\\' . $file->getBasename('.php');
        })->all();
    }

    /**
     * @description Documents an individual controller.
     * @param string $controllerClass Name of the controller class
     * @return string Controller documentation
     * @throws \ReflectionException If the class doesn't exist
     */
    protected function documentController($controllerClass)
    {
        try {
            $reflection = new ReflectionClass($controllerClass);
            $stub = File::get($this->stubPath);

            return strtr($stub, [
                '{{controllerName}}' => $reflection->getShortName(),
                '{{endpoints}}' => $this->getEndpoints($reflection),
            ]);
        } catch (\ReflectionException $e) {
            // Log the error or handle it as needed
            return sprintf("Error documenting controller %s: %s\n", $controllerClass, $e->getMessage());
        } catch (FileNotFoundException $e) {
            // Handle file not found exception
            return sprintf("Error: Stub file not found for controller %s\n", $controllerClass);
        }
    }

    protected function getEndpoints(ReflectionClass $reflection)
    {
        $endpoints = '';
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($this->isEndpointMethod($method)) {
                $endpoints .= $this->documentEndpoint($method);
            }
        }

        return $endpoints;
    }

    /**
     * @description Determines if a method is an API endpoint.
     * @param ReflectionMethod $method Method to evaluate
     * @return bool
     */
    protected function isEndpointMethod(ReflectionMethod $method)
    {
        $httpMethods = ['get', 'post', 'put', 'patch', 'delete'];
        return !$method->isConstructor() && 
               !Str::startsWith($method->getName(), '__') &&
               Str::contains(strtolower($method->getDocComment()), $httpMethods);
    }

    /**
     * @description Documents an individual endpoint.
     * @param ReflectionMethod $method Endpoint method
     * @return string Endpoint documentation
     */
    protected function documentEndpoint(ReflectionMethod $method)
    {
        $docComment = $method->getDocComment();
        $httpMethod = $this->getHttpMethodFromDocComment($docComment);
        $route = $this->getRouteFromDocComment($docComment);
        $description = $this->getDescriptionFromDocComment($docComment);

        return sprintf(
            "- **%s** `%s`: %s\n  %s\n",
            $httpMethod,
            $route,
            $method->getName(),
            $description
        );
    }

    protected function getHttpMethodFromDocComment($docComment)
    {
        if (preg_match('/@method\s+(\w+)/', $docComment, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'GET'; // Default to GET if not specified
    }

    protected function getRouteFromDocComment($docComment)
    {
        if (preg_match('/@route\s+(.+)/', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return '/'; // Default route if not specified
    }

    /**
     * @description Extracts the description from the DocBlock.
     * @param string $docComment Full DocBlock
     * @return string Extracted description
     */
    protected function getDescriptionFromDocComment($docComment)
    {
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No description available.';
    }
}