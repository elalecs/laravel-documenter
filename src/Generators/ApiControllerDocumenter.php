<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * @description Clase para documentar controladores API de Laravel.
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
     * @description Genera la documentación para todos los controladores API.
     * @return string Documentación generada
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
     * @description Obtiene la lista de controladores API.
     * @return array Lista de nombres de clase de controladores
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
     * @description Documenta un controlador individual.
     * @param string $controllerClass Nombre de la clase del controlador
     * @return string Documentación del controlador
     * @throws \ReflectionException Si la clase no existe
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
            // Registrar el error o manejarlo según sea necesario
            return sprintf("Error al documentar el controlador %s: %s\n", $controllerClass, $e->getMessage());
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
     * @description Determina si un método es un endpoint de API.
     * @param ReflectionMethod $method Método a evaluar
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
     * @description Documenta un endpoint individual.
     * @param ReflectionMethod $method Método del endpoint
     * @return string Documentación del endpoint
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
     * @description Extrae la descripción del DocBlock.
     * @param string $docComment DocBlock completo
     * @return string Descripción extraída
     */
    protected function getDescriptionFromDocComment($docComment)
    {
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'Sin descripción disponible.';
    }
}