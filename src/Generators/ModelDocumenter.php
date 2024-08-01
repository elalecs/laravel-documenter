<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use PhpParser\Node;
use PhpParser\NodeFinder;

class ModelDocumenter extends BasePhpParserDocumenter
{
    protected $config;
    protected $stubPath;
    protected $className;
    protected $namespace;
    protected $tableName;
    protected $fillable = [];
    protected $relationships = [];
    protected $scopes = [];
    protected $casts = [];

    public function __construct($config)
    {
        parent::__construct();
        $this->config = $config;
        $this->setStubPath();
    }

    protected function setStubPath()
    {
        $stubsPath = $this->config['stubs_path'] ?? __DIR__.'/../Stubs';
        $this->stubPath = $stubsPath . "/model-documenter.blade.php";
        
        if (!File::exists($this->stubPath)) {
            throw new \RuntimeException("Model documenter stub not found at {$this->stubPath}");
        }
    }

    public function generate()
    {
        $models = $this->getModels();
        $documentation = '';

        foreach ($models as $modelFile) {
            $documentation .= $this->documentModel($modelFile);
        }

        return $documentation;
    }

    protected function getModels()
    {
        $modelPath = $this->config['model_path'] ?? app_path('Models');
        return File::allFiles($modelPath);
    }

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

    protected function convertToStringArray($array)
    {
        return array_map(function ($item) {
            return $item instanceof Node\Scalar\String_ ? $item->value : (string)$item;
        }, $array);
    }

    protected function convertScopesToArray()
    {
        return array_map(function ($scope) {
            return [
                'name' => $scope['name'],
                'description' => $scope['description']
            ];
        }, $this->scopes);
    }

    protected function convertCastsToArray()
    {
        return array_map(function ($value, $key) {
            return [
                'attribute' => $key instanceof Node\Scalar\String_ ? $key->value : (string)$key,
                'type' => $value instanceof Node\Scalar\String_ ? $value->value : (string)$value
            ];
        }, $this->casts, array_keys($this->casts));
    }

    protected function extractModelInfo($ast)
    {
        $nodeFinder = new NodeFinder;
        
        $classNode = $nodeFinder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_;
        });

        if ($classNode) {
            $this->className = $classNode->name->toString();
            $this->extractNamespace($ast);

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

    protected function extractNamespace($ast)
    {
        $nodeFinder = new NodeFinder;
        $namespaceNode = $nodeFinder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Namespace_;
        });

        if ($namespaceNode) {
            $this->namespace = $namespaceNode->name->toString();
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
        } elseif ($propertyName === 'casts' && isset($property->props[0]->default)) {
            $this->casts = $this->extractCasts($property->props[0]->default);
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
            $this->relationships[] = (object)[
                'name' => $method->name->toString(),
                'type' => $relationshipNode->name->toString(),
                'relatedModel' => $this->getRelatedModel($relationshipNode->args[0]->value)
            ];
        }
    }

    protected function getRelatedModel($node)
    {
        if ($node instanceof Node\Expr\ClassConstFetch) {
            return $node->class->toString() . '::class';
        } elseif ($node instanceof Node\Scalar\String_) {
            return $node->value;
        } else {
            return 'Unknown';
        }
    }

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

    protected function getDocComment(Node $node)
    {
        if ($node->getDocComment()) {
            $docComment = $node->getDocComment()->getText();
            if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
                return trim($matches[1]);
            }
        }
        return 'No description provided.';
    }
}