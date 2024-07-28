<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;

/**
 * @description Clase para documentar eventos de Laravel.
 */
class EventDocumenter
{
    protected $config;
    protected $stubPath;

    /**
     * @description Constructor de la clase EventDocumenter.
     * @param array $config Configuración del documentador
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/event.stub';
    }

    /**
     * @description Genera la documentación para todos los eventos.
     * @return string Documentación generada
     */
    public function generate()
    {
        $events = $this->getEvents();
        $documentation = '';

        foreach ($events as $event) {
            $documentation .= $this->documentEvent($event);
        }

        return $documentation;
    }

    /**
     * @description Obtiene la lista de eventos del proyecto.
     * @return array Lista de nombres de clase de eventos
     */
    protected function getEvents()
    {
        $eventPath = $this->config['event_path'] ?? app_path('Events');
        $files = File::allFiles($eventPath);

        return collect($files)->map(function ($file) use ($eventPath) {
            $relativePath = $file->getRelativePath();
            $namespace = str_replace('/', '\\', $relativePath);
            $className = $file->getBasename('.php');
            return "App\\Events\\{$namespace}\\{$className}";
        })->all();
    }

    /**
     * @description Documenta un evento individual.
     * @param string $eventClass Nombre de la clase del evento
     * @return string Documentación del evento
     */
    protected function documentEvent($eventClass)
    {
        $reflection = new ReflectionClass($eventClass);
        $stub = File::get($this->stubPath);

        return strtr($stub, [
            '{{eventName}}' => $reflection->getShortName(),
            '{{description}}' => $this->getEventDescription($reflection),
            '{{properties}}' => $this->getEventProperties($reflection),
            '{{listeners}}' => $this->getEventListeners($eventClass),
        ]);
    }

    /**
     * @description Obtiene la descripción del evento desde su DocBlock.
     * @param ReflectionClass $reflection Reflexión de la clase del evento
     * @return string Descripción del evento
     */
    protected function getEventDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No se proporcionó descripción.';
    }

    /**
     * @description Obtiene las propiedades públicas del evento.
     * @param ReflectionClass $reflection Reflexión de la clase del evento
     * @return string Documentación de las propiedades
     */
    protected function getEventProperties(ReflectionClass $reflection)
    {
        $properties = '';
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $properties .= sprintf("- `%s`\n", $property->getName());
            $docComment = $property->getDocComment();
            if (preg_match('/@var\s+(.+)/', $docComment, $matches)) {
                $properties .= sprintf("  Tipo: %s\n", trim($matches[1]));
            }
            if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
                $properties .= sprintf("  Descripción: %s\n", trim($matches[1]));
            }
        }
        return $properties ?: 'No hay propiedades públicas.';
    }

    /**
     * @description Obtiene los listeners registrados para el evento.
     * @param string $eventClass Nombre de la clase del evento
     * @return string Lista de listeners
     */
    protected function getEventListeners($eventClass)
    {
        $listeners = '';
        try {
            $eventServiceProvider = app()->getProvider('App\Providers\EventServiceProvider');
            
            if (method_exists($eventServiceProvider, 'listens')) {
                $listens = $eventServiceProvider->listens();
                $eventListeners = $listens[$eventClass] ?? [];
                
                foreach ($eventListeners as $listener) {
                    $listeners .= sprintf("- %s\n", $listener);
                }
            }
        } catch (\Exception $e) {
            // Registrar el error o manejarlo según sea necesario
            $listeners = "Error al obtener listeners: " . $e->getMessage() . "\n";
        }
        
        return $listeners ?: 'No hay listeners registrados.';
    }
}