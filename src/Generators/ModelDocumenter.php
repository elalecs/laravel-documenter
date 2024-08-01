<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use PhpParser\Node;
use PhpParser\NodeFinder;

class ModelDocumenter extends BasePhpParserDocumenter
{
    protected $config;
    protected $stubPath;
    protected $className;
    protected $tableName;
    protected $fillable = [];
    protected $relationships = [];

    public function __construct($config)
    {
        parent::__construct();
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/model.stub';
    }

    protected function extractModelInfo($ast)
    {
        $nodeFinder = new NodeFinder;
        
        $classNode = $nodeFinder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_;
        });

        if ($classNode) {
            $this->className = $classNode->name->toString();

            foreach ($classNode->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Property) {
                    $this->extractProperty($stmt);
                } elseif ($stmt instanceof Node\Stmt\ClassMethod) {
                    $this->extractRelationship($stmt);
                }
            }
        }
    }

    protected function extractProperty(Node\Stmt\Property $property)
    {
        $propertyName = $property->props[0]->name->toString();
        
        if ($propertyName === 'table' && isset($property->props[0]->default)) {
            $this->tableName = $property->props[0]->default->value;
        } elseif ($propertyName === 'fillable' && isset($property->props[0]->default)) {
            $this->fillable = array_map(function($item) {
                return $item->value;
            }, $property->props[0]->default->items);
        }
    }

    protected function extractRelationship(Node\Stmt\ClassMethod $method)
    {
        $relationshipMethods = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphMany', 'morphToMany'];
        
        $nodeFinder = new NodeFinder;
        $relationshipNode = $nodeFinder->findFirst($method, function(Node $node) use ($relationshipMethods) {
            return $node instanceof Node\Expr\MethodCall && in_array($node->name->toString(), $relationshipMethods);
        });

        if ($relationshipNode) {
            $this->relationships[] = $method->name->toString();
        }
    }

    public function generate()
    {
        $models = $this->getModels();
        $documentation = '';

        foreach ($models as $model) {
            $documentation .= $this->documentModel($model);
        }

        return $documentation;
    }

    protected function getModels()
    {
        $modelPath = $this->config['model_path'] ?? app_path('Models');
        $files = File::allFiles($modelPath);

        return collect($files)->map(function ($file) use ($modelPath) {
            $relativePath = $file->getRelativePath();
            $namespace = $relativePath ? str_replace('/', '\\', $relativePath) . '\\' : '';
            $className = $file->getBasename('.php');
            return "App\\Models\\{$namespace}{$className}";
        })->all();
    }

    protected function documentModel($modelClass)
    {
        $filePath = $this->getFilePath($modelClass);
        $ast = $this->parseFile($filePath);

        $this->extractModelInfo($ast);

        $stub = File::get($this->stubPath);

        return strtr($stub, [
            '{{modelName}}' => $this->className,
            '{{description}}' => $this->getModelDescription($ast),
            '{{tableName}}' => $this->tableName,
            '{{fillable}}' => implode(', ', array_map(function ($item) {
                return $item instanceof \PhpParser\Node\Scalar\String_ ? $item->value : (string) $item;
            }, $this->fillable)),
            '{{relationships}}' => implode(', ', array_map(function ($item) {
                return $item instanceof \PhpParser\Node\Scalar\String_ ? $item->value : (string) $item;
            }, $this->relationships)),
            '{{casts}}' => $this->getCasts($ast),
            '{{scopes}}' => $this->getScopes($ast),
            '{{attributes}}' => $this->getAttributes($ast),
        ]);
    }

    protected function getFilePath($modelClass)
    {
        $reflectionClass = new \ReflectionClass($modelClass);
        return $reflectionClass->getFileName();
    }

    protected function getModelDescription($ast)
    {
        $finder = new NodeFinder;
        $classNode = $finder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_;
        });

        if ($classNode && $classNode->getDocComment()) {
            $docComment = $classNode->getDocComment()->getText();
            if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
                return trim($matches[1]);
            }
        }

        return 'No description provided.';
    }

    protected function getScopes($ast)
    {
        $scopes = [];
        $finder = new NodeFinder;
        $methodNodes = $finder->find($ast, function(Node $node) {
            return $node instanceof Node\Stmt\ClassMethod && strpos($node->name->name, 'scope') === 0;
        });

        foreach ($methodNodes as $methodNode) {
            $scopeName = lcfirst(substr($methodNode->name->name, 5));
            $scopes[] = $scopeName;
        }

        return implode(', ', $scopes);
    }

    protected function getAttributes($ast)
    {
        $attributes = [];
        $finder = new NodeFinder;
        $propertyNodes = $finder->find($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Property;
        });

        foreach ($propertyNodes as $propertyNode) {
            if ($propertyNode->isPublic()) {
                foreach ($propertyNode->props as $prop) {
                    $attributes[] = $prop->name->name;
                }
            }
        }

        return implode(', ', $attributes);
    }

    protected function getCasts($ast)
    {
        $casts = [];
        $finder = new NodeFinder;
        $propertyNode = $finder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Property && $node->props[0]->name->name === 'casts';
        });

        if ($propertyNode && $propertyNode->props[0]->default instanceof Node\Expr\Array_) {
            foreach ($propertyNode->props[0]->default->items as $item) {
                if ($item instanceof Node\Expr\ArrayItem) {
                    $key = $item->key instanceof Node\Scalar\String_ ? $item->key->value : null;
                    $value = $item->value instanceof Node\Scalar\String_ ? $item->value->value : null;
                    if ($key && $value) {
                        $casts[] = "$key: $value";
                    }
                }
            }
        }

        return implode(', ', $casts);
    }
}