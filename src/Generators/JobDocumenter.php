<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

/**
 * @description Clase para documentar trabajos (jobs) de Laravel.
 */
class JobDocumenter
{
    protected $config;
    protected $stubPath;

    /**
     * @description Constructor de la clase JobDocumenter.
     * @param array $config Configuración del documentador
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/job.stub';
    }

    /**
     * @description Genera la documentación para todos los trabajos.
     * @return string Documentación generada
     */
    public function generate()
    {
        $jobs = $this->getJobs();
        $documentation = '';

        foreach ($jobs as $job) {
            $documentation .= $this->documentJob($job);
        }

        return $documentation;
    }

    /**
     * @description Obtiene la lista de trabajos del proyecto.
     * @return array Lista de nombres de clase de trabajos
     */
    protected function getJobs()
    {
        $jobPath = $this->config['job_path'] ?? app_path('Jobs');
        $files = File::allFiles($jobPath);

        return collect($files)->map(function ($file) use ($jobPath) {
            $relativePath = $file->getRelativePath();
            $namespace = str_replace('/', '\\', $relativePath);
            $className = $file->getBasename('.php');
            return "App\\Jobs\\{$namespace}\\{$className}";
        })->all();
    }

    /**
     * @description Documenta un trabajo individual.
     * @param string $jobClass Nombre de la clase del trabajo
     * @return string Documentación del trabajo
     */
    protected function documentJob($jobClass)
    {
        try {
            $reflection = new ReflectionClass($jobClass);
            $stub = File::get($this->stubPath);

            return strtr($stub, [
                '{{jobName}}' => $reflection->getShortName(),
                '{{queue}}' => $this->getJobQueue($reflection),
                '{{description}}' => $this->getJobDescription($reflection),
                '{{parameters}}' => $this->getConstructorParameters($reflection),
                '{{handleMethod}}' => $this->getHandleMethod($reflection),
                '{{implementedInterfaces}}' => $this->getImplementedInterfaces($reflection),
            ]);
        } catch (\ReflectionException $e) {
            // Registrar el error o manejarlo según sea necesario
            return sprintf("Error al documentar el trabajo %s: %s\n", $jobClass, $e->getMessage());
        }
    }

    /**
     * @description Obtiene la cola de ejecución del trabajo.
     * @param ReflectionClass $reflection Reflexión de la clase del trabajo
     * @return string Cola de ejecución del trabajo
     */
    protected function getJobQueue(ReflectionClass $reflection)
    {
        if ($reflection->hasProperty('queue')) {
            $queueProperty = $reflection->getProperty('queue');
            $queueProperty->setAccessible(true);
            return $queueProperty->getValue($reflection->newInstanceWithoutConstructor());
        }
        return 'default';
    }

    /**
     * @description Obtiene la descripción del trabajo desde su DocBlock.
     * @param ReflectionClass $reflection Reflexión de la clase del trabajo
     * @return string Descripción del trabajo
     */
    protected function getJobDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No se proporcionó descripción.';
    }

    /**
     * @description Obtiene los parámetros del constructor del trabajo.
     * @param ReflectionClass $reflection Reflexión de la clase del trabajo
     * @return string Documentación de los parámetros del constructor
     */
    protected function getConstructorParameters(ReflectionClass $reflection)
    {
        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            return 'No hay parámetros en el constructor.';
        }

        $parameters = '';
        foreach ($constructor->getParameters() as $param) {
            $parameters .= sprintf("- `%s`", $param->getName());
            if ($param->hasType()) {
                $parameters .= sprintf(" (%s)", $param->getType()->getName());
            }
            $docComment = $constructor->getDocComment();
            if (preg_match('/@param\s+\S+\s+\$' . $param->getName() . '\s+(.+)/s', $docComment, $matches)) {
                $parameters .= sprintf(": %s", trim($matches[1]));
            }
            $parameters .= "\n";
        }
        return $parameters;
    }

    /**
     * @description Obtiene información sobre el método handle del trabajo.
     * @param ReflectionClass $reflection Reflexión de la clase del trabajo
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
     * @param ReflectionMethod $method Método a obtener parámetros
     * @return string Documentación de los parámetros
     */
    protected function getMethodParameters(ReflectionMethod $method)
    {
        $parameters = '';
        foreach ($method->getParameters() as $param) {
            $parameters .= sprintf("- `%s`", $param->getName());
            if ($param->hasType()) {
                $parameters .= sprintf(" (%s)", $param->getType()->getName());
            }
            $parameters .= "\n";
        }
        return $parameters ?: 'No parameters.';
    }

    /**
     * @description Obtiene información sobre las interfaces implementadas por el trabajo.
     * @param ReflectionClass $reflection Reflexión de la clase del trabajo
     * @return string Información sobre las interfaces implementadas
     */
    protected function getImplementedInterfaces(ReflectionClass $reflection)
    {
        $interfaces = $reflection->getInterfaceNames();
        if (empty($interfaces)) {
            return "No implementa interfaces específicas.\n";
        }

        $info = "Implementa las siguientes interfaces:\n";
        foreach ($interfaces as $interface) {
            $info .= "- " . $interface . "\n";
        }
        return $info;
    }
}