<?php

namespace YourCompany\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * @description Clase para documentar modelos de Laravel.
 */
class ModelDocumenter
{
    protected $config;
    protected $stubPath;

    /**
     * @description Constructor de la clase ModelDocumenter.
     * @param array $config Configuración del documentador
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/model.stub';
    }

    /**
     * @description Genera la documentación para todos los modelos.
     * @return string Documentación generada
     */
    public function generate()
    {
        $models = $this->getModels();
        $documentation = '';

        foreach ($models as $model) {
            $documentation .= $this->documentModel($model);
        }

        return $documentation;
    }

    /**
     * @description Obtiene la lista de modelos del proyecto.
     * @return array Lista de nombres de clase de modelos
     */
    protected function getModels()
    {
        $modelPath = $this->config['model_path'] ?? app_path('Models');
        $files = File::allFiles($modelPath);

        return collect($files)->map(function ($file) use ($modelPath) {
            $relativePath = $file->getRelativePath();
            $namespace = str_replace('/', '\\', $relativePath);
            $className = $file->getBasename('.php');
            return "App\\Models\\{$namespace}\\{$className}";
        })->all();
    }

    /**
     * @description Documenta un modelo individual.
     * @param string $modelClass Nombre de la clase del modelo
     * @return string Documentación del modelo
     */
    protected function documentModel($modelClass)
    {
        try {
            $reflection = new \ReflectionClass($modelClass);
            $stub = File::get($this->stubPath);

            return strtr($stub, [
                '{{modelName}}' => $reflection->getShortName(),
                '{{description}}' => $this->getModelDescription($reflection),
                '{{tableName}}' => $this->getTableName($modelClass),
                '{{fillable}}' => $this->getFillable($modelClass),
                '{{relationships}}' => $this->getRelationships($reflection),
                '{{scopes}}' => $this->getScopes($reflection),
                '{{attributes}}' => $this->getAttributes($modelClass),
            ]);
        } catch (\ReflectionException $e) {
            // Registrar el error o manejarlo según sea necesario
            return sprintf("Error al documentar el modelo %s: %s\n", $modelClass, $e->getMessage());
        }
    }

    /**
     * @description Obtiene la descripción del modelo desde su DocBlock.
     * @param ReflectionClass $reflection Reflexión de la clase del modelo
     * @return string Descripción del modelo
     */
    protected function getModelDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No se proporcionó descripción.';
    }

    /**
     * @description Obtiene la tabla asociada al modelo.
     * @param string $modelClass Nombre de la clase del modelo
     * @return string Nombre de la tabla
     */
    protected function getTableName($modelClass)
    {
        return (new $modelClass)->getTable();
    }

    /**
     * @description Obtiene los atributos fillable del modelo.
     * @param string $modelClass Nombre de la clase del modelo
     * @return string Lista de atributos fillable
     */
    protected function getFillable($modelClass)
    {
        $fillable = (new $modelClass)->getFillable();
        return implode(', ', $fillable) ?: 'No se definieron atributos fillable.';
    }

    /**
     * @description Obtiene las relaciones del modelo.
     * @param ReflectionClass $reflection Reflexión de la clase del modelo
     * @return string Lista de relaciones
     */
    protected function getRelationships(\ReflectionClass $reflection)
    {
        $relationships = [];
        foreach ($reflection->getMethods() as $method) {
            if ($this->isRelationshipMethod($method)) {
                $relationships[] = $method->getName();
            }
        }
        return implode(', ', $relationships);
    }

    protected function isRelationshipMethod(\ReflectionMethod $method)
    {
        $relationshipMethods = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphMany', 'morphToMany'];
        $methodBody = $this->getMethodBody($method);
        return Str::contains($methodBody, $relationshipMethods);
    }

    protected function getMethodBody(\ReflectionMethod $method)
    {
        $filename = $method->getFileName();
        $start_line = $method->getStartLine() - 1;
        $end_line = $method->getEndLine();
        $length = $end_line - $start_line;

        $source = file($filename);
        $body = implode("", array_slice($source, $start_line, $length));

        return $body;
    }
}