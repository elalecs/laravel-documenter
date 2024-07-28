<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

/**
 * @description Clase para documentar recursos de Filament.
 */
class FilamentResourceDocumenter
{
    protected $config;
    protected $stubPath;

    /**
     * @description Constructor de la clase FilamentResourceDocumenter.
     * @param array $config Configuración del documentador
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/filament-resource.stub';
    }

    /**
     * @description Genera la documentación para todos los recursos de Filament.
     * @return string Documentación generada
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
     * @description Obtiene la lista de recursos de Filament.
     * @return array Lista de nombres de clase de recursos
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
     * @description Documenta un recurso individual de Filament.
     * @param string $resourceClass Nombre de la clase del recurso
     * @return string Documentación del recurso
     */
    protected function documentResource($resourceClass)
    {
        try {
            $reflection = new ReflectionClass($resourceClass);
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
            // Registrar el error o manejarlo según sea necesario
            return sprintf("Error al documentar el recurso %s: %s\n", $resourceClass, $e->getMessage());
        }
    }

    /**
     * @description Obtiene el nombre del modelo asociado al recurso.
     * @param ReflectionClass $reflection Reflexión de la clase del recurso
     * @return string Nombre del modelo
     */
    protected function getModelName(ReflectionClass $reflection)
    {
        $modelMethod = $reflection->getMethod('getModelLabel');
        return $modelMethod->invoke(null);
    }

    /**
     * @description Obtiene los campos del formulario del recurso.
     * @param ReflectionClass $reflection Reflexión de la clase del recurso
     * @return string Documentación de los campos del formulario
     */
    protected function getFormFields(ReflectionClass $reflection)
    {
        $formMethod = $reflection->getMethod('form');
        $form = $formMethod->invoke(null);
        $fields = $form->getSchema();

        return $this->formatSchemaComponents($fields, 'Campos del formulario');
    }

    /**
     * @description Obtiene las columnas de la tabla del recurso.
     * @param ReflectionClass $reflection Reflexión de la clase del recurso
     * @return string Documentación de las columnas de la tabla
     */
    protected function getTableColumns(ReflectionClass $reflection)
    {
        $tableMethod = $reflection->getMethod('table');
        $table = $tableMethod->invoke(null);
        $columns = $table->getColumns();

        return $this->formatSchemaComponents($columns, 'Columnas de la tabla');
    }

    /**
     * @description Obtiene los filtros del recurso.
     * @param ReflectionClass $reflection Reflexión de la clase del recurso
     * @return string Documentación de los filtros
     */
    protected function getFilters(ReflectionClass $reflection)
    {
        if (!$reflection->hasMethod('getFilters')) {
            return 'No filters defined.';
        }

        $filtersMethod = $reflection->getMethod('getFilters');
        $filters = $filtersMethod->invoke(null);

        return $this->formatSchemaComponents($filters, 'Filtros');
    }

    /**
     * @description Obtiene las acciones del recurso.
     * @param ReflectionClass $reflection Reflexión de la clase del recurso
     * @return string Documentación de las acciones
     */
    protected function getActions(ReflectionClass $reflection)
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
     * @description Formatea los componentes del esquema.
     * @param array $components Componentes a formatear
     * @param string $title Título de la sección
     * @return string Componentes formateados
     */
    protected function formatSchemaComponents($components, $title)
    {
        $formatted = "### $title\n\n";
        foreach ($components as $component) {
            $formatted .= sprintf("- **%s**: %s\n", $component->getName(), $this->getComponentDescription($component));
        }
        return $formatted ?: "No se definieron $title.\n";
    }

    /**
     * @description Obtiene la descripción de un componente.
     * @param mixed $component Componente a describir
     * @return string Descripción del componente
     */
    protected function getComponentDescription($component)
    {
        $description = '';

        // Obtener el tipo de componente
        $componentType = class_basename($component);
        $description .= "Tipo: $componentType. ";

        // Obtener el label si está disponible
        if (method_exists($component, 'getLabel')) {
            $label = $component->getLabel();
            $description .= "Etiqueta: '$label'. ";
        }

        // Obtener las validaciones si están disponibles
        if (method_exists($component, 'getValidationRules')) {
            $rules = $component->getValidationRules();
            if (!empty($rules)) {
                $description .= "Validaciones: " . implode(', ', $rules) . ". ";
            }
        }

        // Obtener información adicional específica del tipo de componente
        if ($componentType === 'TextInput') {
            $description .= $this->getTextInputDetails($component);
        } elseif ($componentType === 'Select') {
            $description .= $this->getSelectDetails($component);
        }
        // Agregar más condiciones para otros tipos de componentes según sea necesario

        return trim($description);
    }

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

    private function getSelectDetails($component)
    {
        $details = '';
        if (method_exists($component, 'getOptions')) {
            $options = $component->getOptions();
            if (!empty($options)) {
                $details .= "Opciones: " . implode(', ', array_keys($options)) . ". ";
            }
        }
        return $details;
    }
}