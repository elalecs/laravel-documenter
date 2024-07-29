<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\App;

/**
 * @description Class for documenting Laravel middleware.
 */
class MiddlewareDocumenter
{
    /**
     * @var array The configuration array for the documenter.
     */
    protected $config;

    /**
     * @var string The path to the stub file for middleware.
     */
    protected $stubPath;

    /**
     * @description Constructor of the MiddlewareDocumenter class.
     * @param array $config Documenter configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/middleware.stub';
    }

    /**
     * @description Generates documentation for all middleware.
     * @return string Generated documentation
     */
    public function generate()
    {
        $middlewares = $this->getMiddlewares();
        $documentation = '';

        foreach ($middlewares as $middleware) {
            $documentation .= $this->documentMiddleware($middleware);
        }

        return $documentation;
    }

    /**
     * @description Gets the list of middleware from the project.
     * @return array List of middleware class names
     */
    protected function getMiddlewares()
    {
        $middlewarePath = $this->config['middleware_path'] ?? app_path('Http/Middleware');
        $files = File::allFiles($middlewarePath);

        return collect($files)->map(function ($file) use ($middlewarePath) {
            $relativePath = $file->getRelativePath();
            $namespace = str_replace('/', '\\', $relativePath);
            $className = $file->getBasename('.php');
            return "App\\Http\\Middleware\\{$namespace}\\{$className}";
        })->all();
    }

    /**
     * @description Documents an individual middleware.
     * @param string $middlewareClass Name of the middleware class
     * @return string Documentation of the middleware
     */
    protected function documentMiddleware($middlewareClass)
    {
        try {
            $reflection = new ReflectionClass($middlewareClass);
            $stub = File::get($this->stubPath);

            return strtr($stub, [
                '{{middlewareName}}' => $reflection->getShortName(),
                '{{description}}' => $this->getMiddlewareDescription($reflection),
                '{{handleMethod}}' => $this->getHandleMethod($reflection),
                '{{registration}}' => $this->getMiddlewareRegistration($middlewareClass),
            ]);
        } catch (\ReflectionException $e) {
            // Log the error or handle it as needed
            return sprintf("Error documenting middleware %s: %s\n", $middlewareClass, $e->getMessage());
        }
    }

    /**
     * @description Gets the middleware description from its DocBlock.
     * @param ReflectionClass $reflection Reflection of the middleware class
     * @return string Description of the middleware
     */
    protected function getMiddlewareDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No description provided.';
    }

    /**
     * @description Gets information about the middleware's handle method.
     * @param ReflectionClass $reflection Reflection of the middleware class
     * @return string Documentation of the handle method
     */
    protected function getHandleMethod(ReflectionClass $reflection)
    {
        $handleMethod = $reflection->getMethod('handle');
        $docComment = $handleMethod->getDocComment();

        $description = 'No description provided.';
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            $description = trim($matches[1]);
        }

        return sprintf("Description: %s\n\nParameters:\n%s",
            $description,
            $this->getMethodParameters($handleMethod)
        );
    }

    /**
     * @description Gets the parameters of a method.
     * @param ReflectionMethod $method Method to analyze
     * @return string Documentation of the method parameters
     */
    protected function getMethodParameters(ReflectionMethod $method)
    {
        $parameters = '';
        foreach ($method->getParameters() as $param) {
            $parameters .= sprintf("- `%s`", $param->getName());
            if ($param->hasType()) {
                $parameters .= sprintf(" (%s)", $param->getType()->getName());
            }
            $docComment = $method->getDocComment();
            if (preg_match('/@param\s+\S+\s+\$' . $param->getName() . '\s+(.+)/s', $docComment, $matches)) {
                $parameters .= sprintf(": %s", trim($matches[1]));
            }
            $parameters .= "\n";
        }
        return $parameters ?: 'No parameters.';
    }

    /**
     * @description Gets information about the middleware registration.
     * @param string $middlewareClass Name of the middleware class
     * @return string Information about the middleware registration
     */
    protected function getMiddlewareRegistration($middlewareClass)
    {
        $kernel = App::make(Kernel::class);
        $middlewareGroups = $kernel->getMiddlewareGroups();
        $routeMiddleware = $kernel->getRouteMiddleware();

        $info = "Middleware registration:\n";

        foreach ($middlewareGroups as $group => $middlewares) {
            if (in_array($middlewareClass, $middlewares)) {
                $info .= "- Registered in the '$group' middleware group\n";
            }
        }

        foreach ($routeMiddleware as $key => $middleware) {
            if ($middleware === $middlewareClass) {
                $info .= "- Registered as route middleware with key '$key'\n";
            }
        }

        return $info ?: "- No registration information found for this middleware.\n";
    }
}