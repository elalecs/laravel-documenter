<?php

namespace Elalecs\LaravelDocumenter\Generators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeDumper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FilamentDocumenter extends BasePhpParserDocumenter
{
    protected $config;
    protected $documentation = [];

    public function __construct($config)
    {
        parent::__construct($config);
        $this->config = $config;
    }

    public function generate()
    {
        $resourceFiles = $this->getResourceFiles();

        foreach ($resourceFiles as $file) {
            $this->documentResource($file);
        }

        return $this->formatDocumentation();
    }

    protected function getResourceFiles()
    {
        $path = $this->config['path'] ?? app_path('Filament/Resources');
        return File::allFiles($path);
    }

    protected function documentResource($file)
    {
        $ast = $this->parseFile($file->getPathname());
        $nodeFinder = new NodeFinder;

        $classNode = $nodeFinder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_ && $this->extendsFilamentResource($node);
        });

        if (!$classNode) {
            return;
        }

        $resourceName = $classNode->name->toString();
        $tableMethod = $this->findMethod($classNode, 'table');

        $this->documentation[$resourceName] = [
            'modelClass' => $this->getModelClass($classNode),
            'navigationIcon' => $this->getStaticPropertyValue($classNode, 'navigationIcon'),
            'modelLabel' => $this->getStaticPropertyValue($classNode, 'modelLabel'),
            'navigationLabel' => $this->getStaticPropertyValue($classNode, 'navigationLabel'),
            'pluralModelLabel' => $this->getStaticPropertyValue($classNode, 'pluralModelLabel'),
            'navigationGroup' => $this->getStaticPropertyValue($classNode, 'navigationGroup'),
            'navigationSort' => $this->getStaticPropertyValue($classNode, 'navigationSort'),
            'form' => $this->getForm($classNode),
            'table' => $tableMethod ? $this->extractTableColumns($tableMethod) : [],
            'filters' => $tableMethod ? $this->extractFilters($tableMethod) : [],
            'actions' => $tableMethod ? $this->extractActions($tableMethod) : [],
            'pages' => $this->extractPages($classNode),
        ];
    }

    protected function extendsFilamentResource(Node\Stmt\Class_ $node)
    {
        if ($node->extends) {
            return $node->extends->toString() === 'Filament\Resources\Resource';
        }
        return false;
    }

    protected function getModelClass(Node\Stmt\Class_ $node)
    {
        $modelProperty = $this->findProperty($node, 'model');
        if ($modelProperty && $modelProperty->props[0]->default instanceof Node\Expr\ClassConstFetch) {
            return $modelProperty->props[0]->default->class->toString();
        }
        return 'Unknown';
    }

    protected function getStaticPropertyValue(Node\Stmt\Class_ $node, $propertyName)
    {
        $property = $this->findProperty($node, $propertyName);
        if ($property && $property->props[0]->default instanceof Node\Scalar\String_) {
            return $property->props[0]->default->value;
        }
        return null;
    }

    protected function getForm(Node\Stmt\Class_ $node)
    {
        $formMethod = $this->findMethod($node, 'form');
        if ($formMethod) {
            return $this->extractFormSchema($formMethod);
        }
        return [];
    }

    protected function extractFormSchema(Node\Stmt\ClassMethod $method)
    {
        $schema = [];
        $nodeFinder = new NodeFinder;
        $schemaNodes = $nodeFinder->find($method, function(Node $node) {
            return $node instanceof Node\Expr\MethodCall && $node->name->name === 'schema';
        });

        foreach ($schemaNodes as $schemaNode) {
            if (isset($schemaNode->args[0]) && $schemaNode->args[0]->value instanceof Node\Expr\Array_) {
                foreach ($schemaNode->args[0]->value->items as $item) {
                    if ($item->value instanceof Node\Expr\MethodCall) {
                        $schema[] = $this->extractFormField($item->value);
                    }
                }
            }
        }

        return $schema;
    }

    protected function extractFormField(Node\Expr\MethodCall $node)
    {
        $field = [
            'type' => $node->name->name,
            'name' => '',
            'label' => '',
        ];

        foreach ($node->args as $arg) {
            if ($arg->value instanceof Node\Scalar\String_) {
                $field['name'] = $arg->value->value;
                break;
            }
        }

        $nodeFinder = new NodeFinder;
        $labelNode = $nodeFinder->findFirst($node, function(Node $n) {
            return $n instanceof Node\Expr\MethodCall && $n->name->name === 'label';
        });

        if ($labelNode && isset($labelNode->args[0]) && $labelNode->args[0]->value instanceof Node\Scalar\String_) {
            $field['label'] = $labelNode->args[0]->value->value;
        }

        return $field;
    }

    protected function getTable(Node\Stmt\Class_ $node)
    {
        $tableMethod = $this->findMethod($node, 'table');
        if ($tableMethod) {
            Log::info('Found table method');
            return $this->extractTableColumns($tableMethod);
        }
        Log::warning('Table method not found');
        return [];
    }

    protected function extractTableColumns(Node\Stmt\ClassMethod $method)
    {
        $columns = [];
        $nodeFinder = new NodeFinder;
        
        $returnStmt = $nodeFinder->findFirst($method, function(Node $node) {
            return $node instanceof Node\Stmt\Return_;
        });

        if (!$returnStmt) {
            Log::warning('No return statement found in table method');
            return $columns;
        }

        $columnsNode = $nodeFinder->findFirst($returnStmt, function(Node $node) {
            return $node instanceof Node\Expr\MethodCall && $node->name->name === 'columns';
        });

        if (!$columnsNode || !isset($columnsNode->args[0]) || !$columnsNode->args[0]->value instanceof Node\Expr\Array_) {
            Log::warning('No columns array found in table method');
            return $columns;
        }

        foreach ($columnsNode->args[0]->value->items as $item) {
            if ($item->value instanceof Node\Expr\MethodCall || $item->value instanceof Node\Expr\StaticCall) {
                $column = $this->extractColumnInfo($item->value);
                $columns[] = $column;
            }
        }

        return $columns;
    }

    protected function extractColumnInfo(Node $node, array $column = [])
    {
        if ($node instanceof Node\Expr\StaticCall && $node->name->name === 'make') {
            $column['type'] = $node->class->getLast();
            if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                $column['name'] = $node->args[0]->value->value;
            }
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $methodName = $node->name->name;
            if ($methodName === 'label' && isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                $column['label'] = $node->args[0]->value->value;
            } elseif (in_array($methodName, ['sortable', 'searchable', 'toggleable', 'boolean', 'dateTime'])) {
                $column['attributes'][] = $methodName;
            }
            
            // Procesar el nodo var recursivamente
            if ($node->var instanceof Node\Expr\MethodCall || $node->var instanceof Node\Expr\StaticCall) {
                $column = $this->extractColumnInfo($node->var, $column);
            }
        }

        return $column;
    }

    protected function getRelations(Node\Stmt\Class_ $node)
    {
        $relationsMethod = $this->findMethod($node, 'getRelations');
        if ($relationsMethod) {
            return $this->extractRelations($relationsMethod);
        }
        return [];
    }

    protected function extractRelations(Node\Stmt\ClassMethod $method)
    {
        $relations = [];
        if ($method->stmts[0] instanceof Node\Stmt\Return_) {
            $returnValue = $method->stmts[0]->expr;
            if ($returnValue instanceof Node\Expr\Array_) {
                foreach ($returnValue->items as $item) {
                    if ($item->value instanceof Node\Expr\ClassConstFetch) {
                        $relations[] = $item->value->class->toString();
                    }
                }
            }
        }
        return $relations;
    }

    protected function getFilters(Node\Stmt\Class_ $node)
    {
        $tableMethod = $this->findMethod($node, 'table');
        if ($tableMethod) {
            return $this->extractFilters($tableMethod);
        }
        return [];
    }

    protected function getActions(Node\Stmt\Class_ $node)
    {
        $tableMethod = $this->findMethod($node, 'table');
        if ($tableMethod) {
            return $this->extractActions($tableMethod);
        }
        return [];
    }

    protected function getPages(Node\Stmt\Class_ $node)
    {
        $pagesMethod = $this->findMethod($node, 'getPages');
        if ($pagesMethod) {
            return $this->extractPages($pagesMethod);
        }
        return [];
    }

    protected function extractFilters(Node\Stmt\ClassMethod $method)
    {
        $filters = [];
        $nodeFinder = new NodeFinder;
        
        $filtersNode = $nodeFinder->findFirst($method, function(Node $node) {
            return $node instanceof Node\Expr\MethodCall && $node->name->name === 'filters';
        });

        if (!$filtersNode || !isset($filtersNode->args[0]) || !$filtersNode->args[0]->value instanceof Node\Expr\Array_) {
            return $filters;
        }

        foreach ($filtersNode->args[0]->value->items as $item) {
            if ($item->value instanceof Node\Expr\MethodCall || $item->value instanceof Node\Expr\StaticCall) {
                $filter = $this->extractFilterInfo($item->value);
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    protected function extractFilterInfo(Node $node, array $filter = [])
    {
        if ($node instanceof Node\Expr\StaticCall && $node->name->name === 'make') {
            $filter['type'] = $node->class->getLast();
            if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                $filter['name'] = $node->args[0]->value->value;
            }
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $methodName = $node->name->name;
            if ($methodName === 'label' && isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                $filter['label'] = $node->args[0]->value->value;
            } elseif ($methodName === 'attribute' && isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                $filter['attribute'] = $node->args[0]->value->value;
            } elseif ($methodName === 'relationship' && isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                $filter['relationship'] = $node->args[0]->value->value;
            }
            
            if ($node->var instanceof Node\Expr\MethodCall || $node->var instanceof Node\Expr\StaticCall) {
                $filter = $this->extractFilterInfo($node->var, $filter);
            }
        }

        return $filter;
    }

    protected function extractActions(Node\Stmt\ClassMethod $method)
    {
        $actions = [];
        $nodeFinder = new NodeFinder;
        
        $actionsNode = $nodeFinder->findFirst($method, function(Node $node) {
            return $node instanceof Node\Expr\MethodCall && $node->name->name === 'actions';
        });

        if (!$actionsNode || !isset($actionsNode->args[0]) || !$actionsNode->args[0]->value instanceof Node\Expr\Array_) {
            return $actions;
        }

        foreach ($actionsNode->args[0]->value->items as $item) {
            if ($item->value instanceof Node\Expr\MethodCall || $item->value instanceof Node\Expr\StaticCall) {
                $action = $this->extractActionInfo($item->value);
                $actions[] = $action;
            }
        }

        return $actions;
    }

    protected function extractActionInfo(Node $node, array $action = [])
    {
        if ($node instanceof Node\Expr\StaticCall && $node->name->name === 'make') {
            $action['type'] = $node->class->getLast();
        } elseif ($node instanceof Node\Expr\MethodCall) {
            $methodName = $node->name->name;
            if ($methodName === 'label' && isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
                $action['label'] = $node->args[0]->value->value;
            }
            
            if ($node->var instanceof Node\Expr\MethodCall || $node->var instanceof Node\Expr\StaticCall) {
                $action = $this->extractActionInfo($node->var, $action);
            }
        }

        return $action;
    }

    protected function extractPages(Node\Stmt\Class_ $node)
    {
        $pages = [];
        $pagesMethod = $this->findMethod($node, 'getPages');
        if (!$pagesMethod) {
            return $pages;
        }

        $nodeFinder = new NodeFinder;
        $returnStmt = $nodeFinder->findFirst($pagesMethod, function(Node $n) {
            return $n instanceof Node\Stmt\Return_;
        });

        if (!$returnStmt || !$returnStmt->expr instanceof Node\Expr\Array_) {
            return $pages;
        }

        foreach ($returnStmt->expr->items as $item) {
            if ($item->key instanceof Node\Scalar\String_ && $item->value instanceof Node\Expr\ClassConstFetch) {
                $pages[$item->key->value] = $item->value->class->toString();
            }
        }

        return $pages;
    }

    protected function findProperty(Node\Stmt\Class_ $node, $propertyName)
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Property && $stmt->props[0]->name->name === $propertyName) {
                return $stmt;
            }
        }
        return null;
    }

    protected function findMethod(Node\Stmt\Class_ $node, $methodName)
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->name === $methodName) {
                return $stmt;
            }
        }
        return null;
    }

    protected function formatDocumentation()
    {
        $output = '';
        foreach ($this->documentation as $resourceName => $resource) {
            $output .= "## Filament Resource: $resourceName\n\n";
            $output .= "- Model: {$resource['modelClass']}\n";
            $output .= "- Navigation Icon: {$resource['navigationIcon']}\n";
            $output .= "- Model Label: {$resource['modelLabel']}\n";
            $output .= "- Navigation Label: {$resource['navigationLabel']}\n";
            $output .= "- Plural Model Label: {$resource['pluralModelLabel']}\n";
            $output .= "- Navigation Group: {$resource['navigationGroup']}\n";
            $output .= "- Navigation Sort: {$resource['navigationSort']}\n\n";

            $output .= "### Form Fields:\n";
            foreach ($resource['form'] as $field) {
                $output .= "- {$field['type']}: {$field['name']} (Label: {$field['label']})\n";
            }
            $output .= "\n";

            $output .= "### Table Columns:\n";
            foreach ($resource['table'] as $column) {
                $output .= "- {$column['type']}: {$column['name']}";
                if (!empty($column['label'])) {
                    $output .= " (Label: {$column['label']})";
                }
                $output .= "\n";
                if (!empty($column['attributes'])) {
                    $output .= "  Attributes: " . implode(', ', $column['attributes']) . "\n";
                }
            }
            $output .= "\n";

            $output .= "### Filters:\n";
            foreach ($resource['filters'] as $filter) {
                $output .= "- {$filter['type']}: {$filter['name']}";
                if (!empty($filter['label'])) {
                    $output .= " (Label: {$filter['label']})";
                }
                if (!empty($filter['attribute'])) {
                    $output .= " (Attribute: {$filter['attribute']})";
                }
                if (!empty($filter['relationship'])) {
                    $output .= " (Relationship: {$filter['relationship']})";
                }
                $output .= "\n";
            }
            $output .= "\n";

            $output .= "### Actions:\n";
            foreach ($resource['actions'] as $action) {
                $output .= "- {$action['type']}";
                if (!empty($action['label'])) {
                    $output .= " (Label: {$action['label']})";
                }
                $output .= "\n";
            }
            $output .= "\n";

            $output .= "### Pages:\n";
            foreach ($resource['pages'] as $key => $page) {
                $output .= "- $key: $page\n";
            }
            $output .= "\n";
        }

        return $output;
    }
}