<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Foundation\Application;

/**
 * @description Class for documenting Laravel events.
 */
class EventDocumenter
{
    protected $config;
    protected $stubPath;

    /**
     * @description Constructor of the EventDocumenter class.
     * @param array $config Documenter configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/event.stub';
    }

    /**
     * @description Generates documentation for all events.
     * @return string Generated documentation
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
     * @description Gets the list of events from the project.
     * @return array List of event class names
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
     * @description Documents an individual event.
     * @param string $eventClass Name of the event class
     * @return string Event documentation
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
     * @description Gets the event description from its DocBlock.
     * @param ReflectionClass $reflection Reflection of the event class
     * @return string Event description
     */
    protected function getEventDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No description provided.';
    }

    /**
     * @description Gets the public properties of the event.
     * @param ReflectionClass $reflection Reflection of the event class
     * @return string Documentation of the properties
     */
    protected function getEventProperties(ReflectionClass $reflection)
    {
        $properties = '';
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $properties .= sprintf("- `%s`\n", $property->getName());
            $docComment = $property->getDocComment();
            if (preg_match('/@var\s+(.+)/', $docComment, $matches)) {
                $properties .= sprintf("  Type: %s\n", trim($matches[1]));
            }
            if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
                $properties .= sprintf("  Description: %s\n", trim($matches[1]));
            }
        }
        return $properties ?: 'No public properties.';
    }

    /**
     * @description Gets the registered listeners for the event.
     * @param string $eventClass Name of the event class
     * @return string List of listeners
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
        } catch (Exception $e) {
            // Log the error or handle it as needed
            $listeners = "Error getting listeners: " . $e->getMessage() . "\n";
        }
        
        return $listeners ?: 'No registered listeners.';
    }
}