<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;

/**
 * @description Class for documenting Filament resources.
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
    }

    /**
     * @description Generates documentation for all Filament resources.
     * @return string Generated documentation
     */
    public function generate()
    {
        $resources = $this->getFilamentResources();
        $documentation = '';

        foreach ($resources as $resource) {
            $documentation .= $this->documentResource($resource);
        }

        return $documentation;
    }

    /**
     * @description Gets the list of Filament resources.
     * @return array List of resource class names
     */
    protected function getFilamentResources()
    {
        $resourcePath = $this->config['filament_resource_path'] ?? app_path('Filament/Resources');
        $files = File::allFiles($resourcePath);

        return collect($files)->map(function ($file) use ($resourcePath) {
            $relativePath = $file->getRelativePath();
            $namespace = str_replace('/', '\\', $relativePath);
            $className = $file->getBasename('.php');
            return "App\\Filament\\Resources\\{$namespace}\\{$className}";
        })->all();
    }

    /**
     * @description Documents an individual Filament resource.
     * @param string $resourceClass Name of the resource class
     * @return string Resource documentation
     */
    protected function documentResource($resourceClass)
    {
        try {
            $reflection = new \ReflectionClass($resourceClass);
            $stub = File::get($this->stubPath);

            return strtr($stub, [
                '{{resourceName}}' => $reflection->getShortName(),
                '{{modelName}}' => $this->getModelName($reflection),
                '{{formFields}}' => $this->getFormFields($reflection),
                '{{tableColumns}}' => $this->getTableColumns($reflection),
                '{{filters}}' => $this->getFilters($reflection),
                '{{actions}}' => $this->getActions($reflection),
            ]);
        } catch (\ReflectionException $e) {
            // Log the error or handle it as needed
            return sprintf("Error documenting resource %s: %s\n", $resourceClass, $e->getMessage());
        }
    }

    /**
     * @description Gets the name of the model associated with the resource.
     * @param \ReflectionClass $reflection Reflection of the resource class
     * @return string Model name
     */
    protected function getModelName(\ReflectionClass $reflection)
    {
        $modelMethod = $reflection->getMethod('getModelLabel');
        return $modelMethod->invoke(null);
    }

    /**
     * @description Gets the form fields of the resource.
     * @param \ReflectionClass $reflection Reflection of the resource class
     * @return string Documentation of the form fields
     */
    protected function getFormFields(\ReflectionClass $reflection)
    {
        $formMethod = $reflection->getMethod('form');
        $form = $formMethod->invoke(null);
        $fields = $form->getSchema();

        return $this->formatSchemaComponents($fields, 'Form Fields');
    }

    /**
     * @description Gets the table columns of the resource.
     * @param \ReflectionClass $reflection Reflection of the resource class
     * @return string Documentation of the table columns
     */
    protected function getTableColumns(\ReflectionClass $reflection)
    {
        $tableMethod = $reflection->getMethod('table');
        $table = $tableMethod->invoke(null);
        $columns = $table->getColumns();

        return $this->formatSchemaComponents($columns, 'Table Columns');
    }

    /**
     * @description Gets the filters of the resource.
     * @param \ReflectionClass $reflection Reflection of the resource class
     * @return string Documentation of the filters
     */
    protected function getFilters(\ReflectionClass $reflection)
    {
        if (!$reflection->hasMethod('getFilters')) {
            return 'No filters defined.';
        }

        $filtersMethod = $reflection->getMethod('getFilters');
        $filters = $filtersMethod->invoke(null);

        return $this->formatSchemaComponents($filters, 'Filters');
    }

    /**
     * @description Gets the actions of the resource.
     * @param \ReflectionClass $reflection Reflection of the resource class
     * @return string Documentation of the actions
     */
    protected function getActions(\ReflectionClass $reflection)
    {
        $actions = '';
        $actionMethods = ['getActions', 'getTableActions', 'getHeaderActions'];

        foreach ($actionMethods as $method) {
            if ($reflection->hasMethod($method)) {
                $actionMethod = $reflection->getMethod($method);
                $actionComponents = $actionMethod->invoke(null);
                $actions .= $this->formatSchemaComponents($actionComponents, ucfirst($method));
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

        // Get component type
        $componentType = class_basename($component);
        $description .= "Type: $componentType. ";

        // Get label if available
        if (method_exists($component, 'getLabel')) {
            $label = $component->getLabel();
            $description .= "Label: '$label'. ";
        }

        // Get validations if available
        if (method_exists($component, 'getValidationRules')) {
            $rules = $component->getValidationRules();
            if (!empty($rules)) {
                $description .= "Validations: " . implode(', ', $rules) . ". ";
            }
        }

        // Get additional information specific to the component type
        if ($componentType === 'TextInput') {
            $description .= $this->getTextInputDetails($component);
        } elseif ($componentType === 'Select') {
            $description .= $this->getSelectDetails($component);
        }
        // Add more conditions for other component types as needed

        return trim($description);
    }

    /**
     * @description Gets additional details for TextInput components.
     * @param TextInput $component The TextInput component
     * @return string Additional details for the TextInput
     */
    private function getTextInputDetails($component)
    {
        $details = '';
        if (method_exists($component, 'getPlaceholder')) {
            $placeholder = $component->getPlaceholder();
            if ($placeholder) {
                $details .= "Placeholder: '$placeholder'. ";
            }
        }
        return $details;
    }

    /**
     * @description Gets additional details for Select components.
     * @param Select $component The Select component
     * @return string Additional details for the Select
     */
    private function getSelectDetails($component)
    {
        $details = '';
        if (method_exists($component, 'getOptions')) {
            $options = $component->getOptions();
            if (!empty($options)) {
                $details .= "Options: " . implode(', ', array_keys($options)) . ". ";
            }
        }
        return $details;
    }
}