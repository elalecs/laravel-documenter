<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Filament\Forms\Form;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;

/**
 * @description Class for documenting Filament resources using reflection.
 */
class FilamentResourceDocumenter
{
    /**
     * @var array The configuration array for the documenter.
     */
    protected $config;

    /**
     * @var string The path to the stub file for Filament resources.
     */
    protected $stubPath;

    /**
     * @description Constructor of the FilamentResourceDocumenter class.
     * @param array $config Documenter configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/filament-resource.stub';
        Log::info('FilamentResourceDocumenter initialized');
    }

    /**
     * @description Generates documentation for all Filament resources.
     * @return string Generated documentation
     */
    public function generate()
    {
        Log::info('Starting documentation generation for Filament resources');
        $resources = $this->getFilamentResources();
        $documentation = '';

        foreach ($resources as $resource) {
            Log::info("Documenting resource: $resource");
            $documentation .= $this->documentResource($resource);
        }

        Log::info('Finished generating documentation for Filament resources');
        return $documentation;
    }

    /**
     * @description Gets the list of Filament resources.
     * @return array List of resource class names
     */
    protected function getFilamentResources()
    {
        $resourcePath = $this->config['filament_resource_path'] ?? app_path('Filament/Resources');
        Log::info("Searching for Filament resources in: $resourcePath");
        $files = File::allFiles($resourcePath);

        $resources = collect($files)->map(function ($file) use ($resourcePath) {
            $relativePath = Str::after($file->getPathname(), $resourcePath . DIRECTORY_SEPARATOR);
            $className = Str::replaceLast('.php', '', $relativePath);
            $className = str_replace(DIRECTORY_SEPARATOR, '\\', $className);
            return "App\\Filament\\Resources\\" . $className;
        })->filter(function ($class) {
            $exists = class_exists($class);
            if (!$exists) {
                Log::warning("Class not found: $class");
            }
            return $exists;
        })->values()->all();

        Log::info('Found ' . count($resources) . ' Filament resources');
        return $resources;
    }

    /**
     * @description Documents an individual Filament resource.
     * @param string $resourceClass Name of the resource class
     * @return string Resource documentation
     */
    protected function documentResource($resourceClass)
    {
        try {
            Log::info("Starting documentation for resource: $resourceClass");
            $reflection = new ReflectionClass($resourceClass);
            $stub = File::get($this->stubPath);

            $documentation = strtr($stub, [
                '{{resourceName}}' => $reflection->getShortName(),
                '{{modelName}}' => $this->getModelName($reflection),
                '{{formFields}}' => $this->getFormFields($reflection),
                '{{tableColumns}}' => $this->getTableColumns($reflection),
                '{{filters}}' => $this->getFilters($reflection),
                '{{actions}}' => $this->getActions($reflection),
            ]);

            Log::info("Finished documenting resource: $resourceClass");
            return $documentation;
        } catch (ReflectionException $e) {
            Log::error("Error documenting resource $resourceClass: " . $e->getMessage());
            return sprintf("Error documenting resource %s: %s\n", $resourceClass, $e->getMessage());
        }
    }

    /**
     * @description Gets the name of the model associated with the resource.
     * @param ReflectionClass $reflection Reflection of the resource class
     * @return string Model name
     */
    protected function getModelName(ReflectionClass $reflection)
    {
        if ($reflection->hasMethod('getModelLabel')) {
            $modelMethod = $reflection->getMethod('getModelLabel');
            $modelName = $modelMethod->invoke(null);
            Log::info("Model name for {$reflection->getName()}: $modelName");
            return $modelName;
        }
        Log::warning("Unable to determine model name for {$reflection->getName()}");
        return 'Unknown Model';
    }

    /**
     * @description Gets the form fields of the resource.
     * @param ReflectionClass $reflection Reflection of the resource class
     * @return string Documentation of the form fields
     */
    protected function getFormFields(ReflectionClass $reflection)
    {
        if ($reflection->hasMethod('form')) {
            Log::info("Getting form fields for {$reflection->getName()}");
            $formMethod = $reflection->getMethod('form');
            $livewire = new class extends \Livewire\Component {
                protected $listeners = ['refresh' => '$refresh'];
            };
            $form = new Form($livewire);
            $form = $formMethod->invoke(null, $form);
            $fields = $form->getSchema();
            return $this->formatSchemaComponents($fields, 'Form Fields');
        }
        Log::warning("No form method found for {$reflection->getName()}");
        return 'No form fields defined.';
    }

    /**
     * @description Gets the table columns of the resource.
     * @param ReflectionClass $reflection Reflection of the resource class
     * @return string Documentation of the table columns
     */
    protected function getTableColumns(ReflectionClass $reflection)
    {
        if ($reflection->hasMethod('table')) {
            Log::info("Getting table columns for {$reflection->getName()}");
            $tableMethod = $reflection->getMethod('table');
            $table = $tableMethod->invoke(null);
            $columns = $table->getColumns();
            return $this->formatSchemaComponents($columns, 'Table Columns');
        }
        Log::warning("No table method found for {$reflection->getName()}");
        return 'No table columns defined.';
    }

    /**
     * @description Gets the filters of the resource.
     * @param ReflectionClass $reflection Reflection of the resource class
     * @return string Documentation of the filters
     */
    protected function getFilters(ReflectionClass $reflection)
    {
        if ($reflection->hasMethod('getFilters')) {
            Log::info("Getting filters for {$reflection->getName()}");
            $filtersMethod = $reflection->getMethod('getFilters');
            $filters = $filtersMethod->invoke(null);
            return $this->formatSchemaComponents($filters, 'Filters');
        }
        Log::info("No getFilters method found for {$reflection->getName()}");
        return 'No filters defined.';
    }

    /**
     * @description Gets the actions of the resource.
     * @param ReflectionClass $reflection Reflection of the resource class
     * @return string Documentation of the actions
     */
    protected function getActions(ReflectionClass $reflection)
    {
        Log::info("Getting actions for {$reflection->getName()}");
        $actions = '';
        $actionMethods = ['getActions', 'getTableActions', 'getHeaderActions'];

        foreach ($actionMethods as $method) {
            if ($reflection->hasMethod($method)) {
                Log::info("Processing $method for {$reflection->getName()}");
                $actionMethod = $reflection->getMethod($method);
                $actionComponents = $actionMethod->invoke(null);
                $actions .= $this->formatSchemaComponents($actionComponents, ucfirst($method));
            } else {
                Log::info("$method not found for {$reflection->getName()}");
            }
        }

        return $actions ?: 'No actions defined.';
    }

    /**
     * @description Formats the schema components.
     * @param array $components Components to format
     * @param string $title Section title
     * @return string Formatted components
     */
    protected function formatSchemaComponents($components, $title)
    {
        Log::info("Formatting schema components for $title");
        $formatted = "### $title\n\n";
        foreach ($components as $component) {
            $formatted .= sprintf("- **%s**: %s\n", $component->getName(), $this->getComponentDescription($component));
        }
        return $formatted ?: "No $title defined.\n";
    }

    /**
     * @description Gets the description of a component.
     * @param mixed $component Component to describe
     * @return string Component description
     */
    protected function getComponentDescription($component)
    {
        $description = '';

        $componentType = class_basename($component);
        $description .= "Type: $componentType. ";

        if (method_exists($component, 'getLabel')) {
            $label = $component->getLabel();
            $description .= "Label: '$label'. ";
        }

        if (method_exists($component, 'getValidationRules')) {
            $rules = $component->getValidationRules();
            if (!empty($rules)) {
                $description .= "Validations: " . implode(', ', $rules) . ". ";
            }
        }

        Log::info("Generated description for component: $componentType");
        return trim($description);
    }
}