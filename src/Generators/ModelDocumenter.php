<?php

/**
 * This file contains the ModelDocumenter class which is responsible for generating
 * documentation for Laravel Eloquent models.
 */

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeDumper;

/**
 * Class ModelDocumenter
 *
 * This class extends BasePhpParserDocumenter and provides functionality to generate
 * documentation for Laravel Eloquent models by parsing and analyzing their structure.
 *
 * @package Elalecs\LaravelDocumenter\Generators
 */
class ModelDocumenter extends BasePhpParserDocumenter
{
    /**
     * Configuration array for the model documenter.
     *
     * @var array
     */
    protected $config;

    /**
     * Path to the stub file used for documentation generation.
     *
     * @var string
     */
    protected $stubPath;

    /**
     * Name of the model class being documented.
     *
     * @var string
     */
    protected $className;

    /**
     * Namespace of the model class being documented.
     *
     * @var string
     */
    protected $namespace;

    /**
     * Name of the database table associated with the model.
     *
     * @var string
     */
    protected $tableName;

    /**
     * Array of fillable attributes for the model.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * Array of relationships defined in the model.
     *
     * @var array
     */
    protected $relationships = [];

    /**
     * Array of scopes defined in the model.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * Array of attribute casts defined in the model.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * ModelDocumenter constructor.
     *
     * @param array $config Configuration array for the model documenter
     */
    public function __construct($config)
    {
        parent::__construct($config);
        $this->config = $config;
        $this->setStubPath();
        $this->log('info', 'ModelDocumenter initialized');
    }

    /**
     * Set the stub path for the model documenter
     *
     * @throws \RuntimeException If the stub file is not found
     * @return void
     */
    protected function setStubPath()
    {
        $stubsPath = $this->config['stubs_path'] ?? __DIR__.'/../Stubs';
        $this->stubPath = $stubsPath . "/model-documenter.blade.php";
        
        if (!File::exists($this->stubPath)) {
            throw new \RuntimeException("Model documenter stub not found at {$this->stubPath}");
        }
        $this->log('info', 'Stub path set');
    }

    /**
     * Generate the model documentation
     *
     * @return string The generated documentation as a string
     */
    public function generate()
    {
        $models = $this->getModels();
        $documentation = '';

        foreach ($models as $modelFile) {
            $documentation .= $this->documentModel($modelFile);
        }

        return $documentation;
    }

    /**
     * Get the model files
     *
     * @return array An array of SplFileInfo objects representing the model files
     */
    protected function getModels()
    {
        $modelPath = $this->config['model_path'] ?? app_path('Models');
        return File::allFiles($modelPath);
    }

    /**
     * Document a single model
     *
     * @param \SplFileInfo $modelFile The model file to document
     * @return string The generated documentation for the model
     */
    protected function documentModel($modelFile)
    {
        $ast = $this->parseFile($modelFile->getPathname());

        $this->extractModelInfo($ast);

        return View::file($this->stubPath, [
            'modelName' => $this->className,
            'namespace' => $this->namespace,
            'description' => $this->getModelDescription($ast),
            'tableName' => $this->tableName,
            'fillable' => $this->convertToStringArray($this->fillable),
            'relationships' => $this->relationships,
            'scopes' => $this->convertScopesToArray(),
            'casts' => $this->convertCastsToArray(),
        ])->render();
    }

    /**
     * Convert scopes to an array
     *
     * @return array An array of scopes with their names and descriptions
     */
    protected function convertScopesToArray()
    {
        return array_map(function ($scope) {
            return [
                'name' => $scope['name'],
                'description' => $scope['description']
            ];
        }, $this->scopes);
    }

    /**
     * Convert casts to an array
     *
     * @return array An array of casts with their attributes and types
     */
    protected function convertCastsToArray()
    {
        return array_map(function ($value, $key) {
            return [
                'attribute' => $key instanceof Node\Scalar\String_ ? $key->value : (string)$key,
                'type' => $value instanceof Node\Scalar\String_ ? $value->value : (string)$value
            ];
        }, $this->casts, array_keys($this->casts));
    }

    /**
     * Extract model information from the AST
     *
     * @param array $ast The Abstract Syntax Tree of the model file
     * @return void
     */
    protected function extractModelInfo($ast)
    {
        $nodeFinder = new NodeFinder;
        
        $classNode = $nodeFinder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_;
        });

        if ($classNode) {
            $this->className = $classNode->name->toString();
            $this->namespace = $this->extractNamespace($ast);

            foreach ($classNode->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Property) {
                    $this->extractProperty($stmt);
                } elseif ($stmt instanceof Node\Stmt\ClassMethod) {
                    $this->extractRelationship($stmt);
                    $this->extractScope($stmt);
                }
            }
        }
    }

    /**
     * Extract property information
     *
     * @param Node\Stmt\Property $property The property node to extract information from
     * @return void
     */
    protected function extractProperty(Node\Stmt\Property $property)
    {
        $propertyName = $property->props[0]->name->toString();
        
        if ($propertyName === 'table' && isset($property->props[0]->default)) {
            $this->tableName = $property->props[0]->default->value;
        } elseif ($propertyName === 'fillable' && isset($property->props[0]->default)) {
            $this->fillable = array_map(function($item) {
                return $item->value;
            }, $property->props[0]->default->items);
        } elseif ($propertyName === 'casts' && isset($property->props[0]->default)) {
            $this->casts = $this->extractCasts($property->props[0]->default);
        }
    }

    /**
     * Extract relationship information
     *
     * @param Node\Stmt\ClassMethod $method The method node to extract relationship information from
     * @return void
     */
    protected function extractRelationship(Node\Stmt\ClassMethod $method)
    {
        $relationshipMethods = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphMany', 'morphToMany'];
        
        $nodeFinder = new NodeFinder;
        $relationshipNode = $nodeFinder->findFirst($method, function(Node $node) use ($relationshipMethods) {
            return $node instanceof Node\Expr\MethodCall && in_array($node->name->toString(), $relationshipMethods);
        });

        if ($relationshipNode) {
            $this->relationships[] = (object)[
                'name' => $method->name->toString(),
                'type' => $relationshipNode->name->toString(),
                'relatedModel' => $this->getRelatedModel($relationshipNode->args[0]->value)
            ];
        }
    }

    /**
     * Extract scope information
     *
     * @param Node\Stmt\ClassMethod $method The method node to extract scope information from
     * @return void
     */
    protected function extractScope(Node\Stmt\ClassMethod $method)
    {
        if (strpos($method->name->toString(), 'scope') === 0) {
            $scopeName = lcfirst(substr($method->name->toString(), 5));
            $this->scopes[] = [
                'name' => $scopeName,
                'description' => $this->getDocComment($method)
            ];
        }
    }

    /**
     * Extract casts information
     *
     * @param Node\Expr\Array_ $node The array node containing cast information
     * @return array An array of casts with their attributes and types
     */
    protected function extractCasts($node)
    {
        $casts = [];
        if ($node instanceof Node\Expr\Array_) {
            foreach ($node->items as $item) {
                if ($item instanceof Node\Expr\ArrayItem) {
                    $key = $item->key instanceof Node\Scalar\String_ ? $item->key->value : null;
                    $value = $item->value instanceof Node\Scalar\String_ ? $item->value->value : null;
                    if ($key && $value) {
                        $casts[$key] = $value;
                    }
                }
            }
        }
        return $casts;
    }

    /**
     * Get the model description from the docblock
     *
     * @param array $ast The Abstract Syntax Tree of the model file
     * @return string The extracted model description or a default message if not found
     */
    protected function getModelDescription($ast)
    {
        $finder = new NodeFinder;
        $classNode = $finder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_;
        });

        if ($classNode && $classNode->getDocComment()) {
            $docComment = $classNode->getDocComment()->getText();
            
            // Remove the opening and closing tags of the doc comment
            $docComment = preg_replace('/^\/\*\*|\*\/$/s', '', $docComment);
            
            // Remove asterisks at the beginning of each line
            $docComment = preg_replace('/^\s*\*\s?/m', '', $docComment);
            
            // Remove the class name line if it exists
            $docComment = preg_replace('/^Class\s+\w+\s*$/m', '', $docComment);
            
            // Remove any @package or other tags
            $docComment = preg_replace('/@\w+.*$/m', '', $docComment);
            
            // Trim any extra whitespace
            $description = trim($docComment);
            
            if (!empty($description)) {
                return $description;
            }
        }

        return 'No description provided.';
    }
}