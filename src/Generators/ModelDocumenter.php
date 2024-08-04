<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use PhpParser\Node;
use PhpParser\NodeFinder;
use Illuminate\Support\Facades\Log;

/**
 * Class ModelDocumenter
 * @package Elalecs\LaravelDocumenter\Generators
 */
class ModelDocumenter extends BasePhpParserDocumenter
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $stubPath;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @var array
     */
    protected $fillable = [];

    /**
     * @var array
     */
    protected $relationships = [];

    /**
     * @var array
     */
    protected $scopes = [];

    /**
     * @var array
     */
    protected $casts = [];

    /**
     * ModelDocumenter constructor.
     * @param array $config
     */
    public function __construct($config)
    {
        parent::__construct($config);
        $this->config = $config;
        $this->setStubPath();
        Log::info('ModelDocumenter initialized');
    }

    /**
     * Set the stub path for the model documenter
     */
    protected function setStubPath()
    {
        $stubsPath = $this->config['stubs_path'] ?? __DIR__.'/../Stubs';
        $this->stubPath = $stubsPath . "/model-documenter.blade.php";
        
        if (!File::exists($this->stubPath)) {
            throw new \RuntimeException("Model documenter stub not found at {$this->stubPath}");
        }
        Log::info('Stub path set');
    }

    /**
     * Generate the model documentation
     * @return string
     */
    public function generate()
    {
        Log::info('Generating model documentation');
        $models = $this->getModels();
        $documentation = '';

        foreach ($models as $modelFile) {
            $documentation .= $this->documentModel($modelFile);
        }

        return $documentation;
    }

    /**
     * Get the model files
     * @return array
     */
    protected function getModels()
    {
        Log::info('Getting model files');
        $modelPath = $this->config['model_path'] ?? app_path('Models');
        return File::allFiles($modelPath);
    }

    /**
     * Document a single model
     * @param \SplFileInfo $modelFile
     * @return string
     */
    protected function documentModel($modelFile)
    {
        Log::info('Documenting model: ' . $modelFile->getFilename());
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
     * @return array
     */
    protected function convertScopesToArray()
    {
        Log::info('Converting scopes to array');
        return array_map(function ($scope) {
            return [
                'name' => $scope['name'],
                'description' => $scope['description']
            ];
        }, $this->scopes);
    }

    /**
     * Convert casts to an array
     * @return array
     */
    protected function convertCastsToArray()
    {
        Log::info('Converting casts to array');
        return array_map(function ($value, $key) {
            return [
                'attribute' => $key instanceof Node\Scalar\String_ ? $key->value : (string)$key,
                'type' => $value instanceof Node\Scalar\String_ ? $value->value : (string)$value
            ];
        }, $this->casts, array_keys($this->casts));
    }

    /**
     * Extract model information from the AST
     * @param array $ast
     */
    protected function extractModelInfo($ast)
    {
        Log::info('Extracting model information');
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
     * @param Node\Stmt\Property $property
     */
    protected function extractProperty(Node\Stmt\Property $property)
    {
        Log::info('Extracting property information');
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
     * @param Node\Stmt\ClassMethod $method
     */
    protected function extractRelationship(Node\Stmt\ClassMethod $method)
    {
        Log::info('Extracting relationship information');
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
     * @param Node\Stmt\ClassMethod $method
     */
    protected function extractScope(Node\Stmt\ClassMethod $method)
    {
        Log::info('Extracting scope information');
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
     * @param Node\Expr\Array_ $node
     * @return array
     */
    protected function extractCasts($node)
    {
        Log::info('Extracting casts information');
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
     * @param array $ast
     * @return string
     */
    protected function getModelDescription($ast)
    {
        Log::info('Getting model description');
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
}