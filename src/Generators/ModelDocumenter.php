<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;

/**
 * @description Class for documenting Laravel models.
 */
class ModelDocumenter
{
    /**
     * @var array The configuration array for the documenter.
     */
    protected $config;

    /**
     * @var string The path to the stub file for models.
     */
    protected $stubPath;

    /**
     * @description Constructor of the ModelDocumenter class.
     * @param array $config Documenter configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/model.stub';
    }

    /**
     * @description Generates documentation for all models.
     * @return string Generated documentation
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
     * @description Gets the list of models from the project.
     * @return array List of model class names
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
     * @description Documents an individual model.
     * @param string $modelClass Name of the model class
     * @return string Documentation of the model
     */
    protected function documentModel($modelClass)
    {
        try {
            $reflection = new ReflectionClass($modelClass);
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
        } catch (ReflectionException $e) {
            // Log the error or handle it as needed
            return sprintf("Error documenting model %s: %s\n", $modelClass, $e->getMessage());
        }
    }

    /**
     * @description Gets the model description from its DocBlock.
     * @param ReflectionClass $reflection Reflection of the model class
     * @return string Description of the model
     */
    protected function getModelDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No description provided.';
    }

    /**
     * @description Gets the table associated with the model.
     * @param string $modelClass Name of the model class
     * @return string Name of the table
     */
    protected function getTableName($modelClass)
    {
        return (new $modelClass)->getTable();
    }

    /**
     * @description Gets the fillable attributes of the model.
     * @param string $modelClass Name of the model class
     * @return string List of fillable attributes
     */
    protected function getFillable($modelClass)
    {
        $fillable = (new $modelClass)->getFillable();
        return implode(', ', $fillable) ?: 'No fillable attributes defined.';
    }

    /**
     * @description Gets the relationships of the model.
     * @param ReflectionClass $reflection Reflection of the model class
     * @return string List of relationships
     */
    protected function getRelationships(ReflectionClass $reflection)
    {
        $relationships = [];
        foreach ($reflection->getMethods() as $method) {
            if ($this->isRelationshipMethod($method)) {
                $relationships[] = $method->getName();
            }
        }
        return implode(', ', $relationships);
    }

    /**
     * @description Determines if a method is a relationship method.
     * @param ReflectionMethod $method The method to check
     * @return bool True if the method is a relationship method, false otherwise
     */
    protected function isRelationshipMethod(ReflectionMethod $method)
    {
        $relationshipMethods = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphMany', 'morphToMany'];
        $methodBody = $this->getMethodBody($method);
        return Str::contains($methodBody, $relationshipMethods);
    }

    /**
     * @description Gets the body of a method.
     * @param ReflectionMethod $method The method to get the body from
     * @return string The body of the method
     */
    protected function getMethodBody(ReflectionMethod $method)
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