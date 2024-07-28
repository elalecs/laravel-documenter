<?php

/**
 * @description Clase para documentar middleware de Laravel.
 */
namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class MiddlewareDocumenter
{
    protected $config;
    protected $stubPath;

    /**
     * @description Constructor de la clase MiddlewareDocumenter.
     * @param array $config Configuración del documentador
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/middleware.stub';
    }

    /**
     * @description Genera la documentación para todos los middleware.
     * @return string Documentación generada
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
     * @description Obtiene la lista de middleware del proyecto.
     * @return array Lista de nombres de clase de middleware
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
     * @description Documenta un middleware individual.
     * @param string $middlewareClass Nombre de la clase del middleware
     * @return string Documentación del middleware
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
            // Registrar el error o manejarlo según sea necesario
            return sprintf("Error al documentar el middleware %s: %s\n", $middlewareClass, $e->getMessage());
        }
    }

    /**
     * @description Obtiene la descripción del middleware desde su DocBlock.
     * @param ReflectionClass $reflection Reflexión de la clase del middleware
     * @return string Descripción del middleware
     */
    protected function getMiddlewareDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No se proporcionó descripción.';
    }

    /**
     * @description Obtiene información sobre el método handle del middleware.
     * @param ReflectionClass $reflection Reflexión de la clase del middleware
     * @return string Documentación del método handle
     */
    protected function getHandleMethod(ReflectionClass $reflection)
    {
        $handleMethod = $reflection->getMethod('handle');
        $docComment = $handleMethod->getDocComment();

        $description = 'No se proporcionó descripción.';
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            $description = trim($matches[1]);
        }

        return sprintf("Descripción: %s\n\nParámetros:\n%s",
            $description,
            $this->getMethodParameters($handleMethod)
        );
    }

    /**
     * @description Obtiene los parámetros de un método.
     * @param ReflectionMethod $method Método a analizar
     * @return string Documentación de los parámetros del método
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
        return $parameters ?: 'No hay parámetros.';
    }

    /**
     * @description Obtiene información sobre el registro del middleware.
     * @param string $middlewareClass Nombre de la clase del middleware
     * @return string Información sobre el registro del middleware
     */
    protected function getMiddlewareRegistration($middlewareClass)
    {
        $kernel = app()->make(\Illuminate\Contracts\Http\Kernel::class);
        $middlewareGroups = $kernel->getMiddlewareGroups();
        $routeMiddleware = $kernel->getRouteMiddleware();

        $info = "Registro del middleware:\n";

        foreach ($middlewareGroups as $group => $middlewares) {
            if (in_array($middlewareClass, $middlewares)) {
                $info .= "- Registrado en el grupo de middleware '$group'\n";
            }
        }

        foreach ($routeMiddleware as $key => $middleware) {
            if ($middleware === $middlewareClass) {
                $info .= "- Registrado como middleware de ruta con la clave '$key'\n";
            }
        }

        return $info ?: "- No se encontró información de registro para este middleware.\n";
    }
}