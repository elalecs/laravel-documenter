<?php

/**
 * This file contains the FilamentDocumenter class, which is responsible for generating
 * documentation for Filament resources using PHP Parser.
 */

namespace Elalecs\LaravelDocumenter\Generators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeDumper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Class FilamentDocumenter
 *
 * This class extends BasePhpParserDocumenter and provides functionality to generate
 * documentation for Filament resources.
 */
class FilamentDocumenter extends BasePhpParserDocumenter
{
    /**
     * @var array Configuration array for the documenter
     */
    protected $config;

    /**
     * @var array Generated documentation storage
     */
    protected $documentation = [];

    /**
     * Constructor for FilamentDocumenter
     *
     * @param array $config Configuration array for the documenter
     */
    public function __construct($config)
    {
        parent::__construct($config);
        $this->config = $config;
    }

    /**
     * Generate documentation for Filament resources
     *
     * @return string Formatted documentation
     */
    public function generate()
    {
        $filamentPath = $this->config['path'] ?? app_path('Filament');

        if (!File::isDirectory($filamentPath)) {
            $this->log('info', 'Filament folder not found. Skipping Filament documentation.');
            return "No Filament resources found to document.\n";
        }

        $resourceFiles = $this->getResourceFiles();

        if (empty($resourceFiles)) {
            $this->log('info', 'No Filament resource files found.');
            return "No Filament resource files found to document.\n";
        }

        foreach ($resourceFiles as $file) {
            $this->documentResource($file);
        }

        return $this->formatDocumentation();
    }

    /**
     * Get all resource files
     *
     * @return array List of resource files
     */
    protected function getResourceFiles()
    {
        $path = $this->config['path'] ?? app_path('Filament/Resources');

        if (!File::isDirectory($path)) {
            $this->log('info', "The Filament resources folder does not exist: $path");
            return [];
        }

        return File::allFiles($path);
    }

    /**
     * Document a single resource
     *
     * @param \SplFileInfo $file Resource file
     */
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

    /**
     * Check if the class extends Filament Resource
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return bool True if extends Filament Resource, otherwise false
     */
    protected function extendsFilamentResource(Node\Stmt\Class_ $node)
    {
        if ($node->extends) {
            return $node->extends->toString() === 'Filament\Resources\Resource';
        }
        return false;
    }

    /**
     * Get the model class of the resource
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return string Model class name
     */
    protected function getModelClass(Node\Stmt\Class_ $node)
    {
        $modelProperty = $this->findProperty($node, 'model');
        if ($modelProperty && $modelProperty->props[0]->default instanceof Node\Expr\ClassConstFetch) {
            return $modelProperty->props[0]->default->class->toString();
        }
        return 'Unknown';
    }

    /**
     * Get the value of a static property
     *
     * @param Node\Stmt\Class_ $node Class node
     * @param string $propertyName Property name
     * @return string|null Property value or null if not found
     */
    protected function getStaticPropertyValue(Node\Stmt\Class_ $node, $propertyName)
    {
        $property = $this->findProperty($node, $propertyName);
        if ($property && $property->props[0]->default instanceof Node\Scalar\String_) {
            return $property->props[0]->default->value;
        }
        return null;
    }

    /**
     * Get the form schema of the resource
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return array Form schema
     */
    protected function getForm(Node\Stmt\Class_ $node)
    {
        $formMethod = $this->findMethod($node, 'form');
        if ($formMethod) {
            return $this->extractFormSchema($formMethod);
        }
        return [];
    }

    /**
     * Extract the form schema from the method
     *
     * @param Node\Stmt\ClassMethod $method Method node
     * @return array Form schema
     */
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
                    $this->extractSchemaItem($item->value, $schema, null, $nodeFinder);
                }
            }
        }
    
        return $schema;
    }
    
    /**
     * Extract schema item information
     *
     * @param Node $node Node to extract information from
     * @param array $schema Reference to the schema array
     * @param string|null $parentComponent Parent component type
     * @param NodeFinder $nodeFinder NodeFinder instance
     */
    protected function extractSchemaItem($node, &$schema, $parentComponent = null, NodeFinder $nodeFinder)
    {
        if ($node instanceof Node\Expr\MethodCall) {
            $componentType = $this->getComponentType($node);
            $field = [
                'type' => $componentType,
                'name' => $this->getFieldName($node),
                'label' => $this->getFieldLabel($node),
            ];
    
            if ($parentComponent) {
                $field['parent'] = $parentComponent;
            }
    
            $schema[] = $field;
    
            // Recursively extract nested schema
            $schemaMethod = $nodeFinder->findFirst($node, function(Node $n) {
                return $n instanceof Node\Expr\MethodCall && $n->name->name === 'schema';
            });
    
            if ($schemaMethod && isset($schemaMethod->args[0]) && $schemaMethod->args[0]->value instanceof Node\Expr\Array_) {
                foreach ($schemaMethod->args[0]->value->items as $item) {
                    $this->extractSchemaItem($item->value, $schema, $componentType, $nodeFinder);
                }
            }
        }
    }
    
    /**
     * Get the component type from a method call node
     *
     * @param Node\Expr\MethodCall $node Method call node
     * @return string Component type
     */
    protected function getComponentType(Node\Expr\MethodCall $node)
    {
        while ($node->var instanceof Node\Expr\MethodCall) {
            $node = $node->var;
        }
        
        if ($node->var instanceof Node\Expr\StaticCall) {
            return $node->var->class->getLast();
        }
        
        return 'Unknown';
    }
    
    /**
     * Get the field name from a method call node
     *
     * @param Node\Expr\MethodCall $node Method call node
     * @return string Field name
     */
    protected function getFieldName(Node\Expr\MethodCall $node)
    {
        $makeMethod = $this->findMethodCall($node, 'make');
        if ($makeMethod && isset($makeMethod->args[0]) && $makeMethod->args[0]->value instanceof Node\Scalar\String_) {
            return $makeMethod->args[0]->value->value;
        }
        return '';
    }
    
    /**
     * Get the field label from a method call node
     *
     * @param Node\Expr\MethodCall $node Method call node
     * @return string Field label
     */
    protected function getFieldLabel(Node\Expr\MethodCall $node)
    {
        $labelMethod = $this->findMethodCall($node, 'label');
        if ($labelMethod && isset($labelMethod->args[0]) && $labelMethod->args[0]->value instanceof Node\Scalar\String_) {
            return $labelMethod->args[0]->value->value;
        }
        return '';
    }
    
    /**
     * Find a specific method call in a chain of method calls
     *
     * @param Node\Expr\MethodCall $node Method call node
     * @param string $methodName Method name to find
     * @return Node\Expr\MethodCall|null Found method call node or null
     */
    protected function findMethodCall(Node\Expr\MethodCall $node, $methodName)
    {
        if ($node->name->name === $methodName) {
            return $node;
        }
        
        if ($node->var instanceof Node\Expr\MethodCall) {
            return $this->findMethodCall($node->var, $methodName);
        }
        
        return null;
    }

    /**
     * Get the table columns of the resource
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return array Table columns
     */
    protected function getTable(Node\Stmt\Class_ $node)
    {
        $tableMethod = $this->findMethod($node, 'table');
        if ($tableMethod) {
            $this->log('info', 'Found table method');
            return $this->extractTableColumns($tableMethod);
        }
        $this->log('warning', 'Table method not found');
        return [];
    }

    /**
     * Extract table columns from the method
     *
     * @param Node\Stmt\ClassMethod $method Method node
     * @return array Table columns
     */
    protected function extractTableColumns(Node\Stmt\ClassMethod $method)
    {
        $columns = [];
        $nodeFinder = new NodeFinder;
        
        $returnStmt = $nodeFinder->findFirst($method, function(Node $node) {
            return $node instanceof Node\Stmt\Return_;
        });

        if (!$returnStmt) {
            $this->log('warning', 'No return statement found in table method');
            return $columns;
        }

        $columnsNode = $nodeFinder->findFirst($returnStmt, function(Node $node) {
            return $node instanceof Node\Expr\MethodCall && $node->name->name === 'columns';
        });

        if (!$columnsNode || !isset($columnsNode->args[0]) || !$columnsNode->args[0]->value instanceof Node\Expr\Array_) {
            $this->log('warning', 'No columns array found in table method');
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

    /**
     * Extract column information
     *
     * @param Node $node Node
     * @param array $column Column information
     * @return array Column information
     */
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
            
            // Process the var node recursively
            if ($node->var instanceof Node\Expr\MethodCall || $node->var instanceof Node\Expr\StaticCall) {
                $column = $this->extractColumnInfo($node->var, $column);
            }
        }

        return $column;
    }

    /**
     * Get the relations of the resource
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return array Relations
     */
    protected function getRelations(Node\Stmt\Class_ $node)
    {
        $relationsMethod = $this->findMethod($node, 'getRelations');
        if ($relationsMethod) {
            return $this->extractRelations($relationsMethod);
        }
        return [];
    }

    /**
     * Extract relations from the method
     *
     * @param Node\Stmt\ClassMethod $method Method node
     * @return array Relations
     */
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

    /**
     * Get the filters of the resource
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return array Filters
     */
    protected function getFilters(Node\Stmt\Class_ $node)
    {
        $tableMethod = $this->findMethod($node, 'table');
        if ($tableMethod) {
            return $this->extractFilters($tableMethod);
        }
        return [];
    }

    /**
     * Extract filters from the method
     *
     * @param Node\Stmt\ClassMethod $method Method node
     * @return array Filters
     */
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

    /**
     * Extract filter information
     *
     * @param Node $node Node
     * @param array $filter Filter information
     * @return array Filter information
     */
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

    /**
     * Get the actions of the resource
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return array Actions
     */
    protected function getActions(Node\Stmt\Class_ $node)
    {
        $tableMethod = $this->findMethod($node, 'table');
        if ($tableMethod) {
            return $this->extractActions($tableMethod);
        }
        return [];
    }

    /**
     * Extract actions from the method
     *
     * @param Node\Stmt\ClassMethod $method Method node
     * @return array Actions
     */
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

    /**
     * Extract action information
     *
     * @param Node $node Node
     * @param array $action Action information
     * @return array Action information
     */
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

    /**
     * Get the pages of the resource
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return array Pages
     */
    protected function getPages(Node\Stmt\Class_ $node)
    {
        $pagesMethod = $this->findMethod($node, 'getPages');
        if ($pagesMethod) {
            return $this->extractPages($pagesMethod);
        }
        return [];
    }

    /**
     * Extract pages from the method
     *
     * @param Node\Stmt\Class_ $node Class node
     * @return array Pages
     */
    protected function extractPages(Node\Stmt\Class_ $node)
    {
        $pages = [];
        $pagesMethod = $this->findMethod($node, 'getPages');
        if (!$pagesMethod) {
            $this->log('warning', 'getPages method not found');
            return $pages;
        }

        $nodeFinder = new NodeFinder;
        $returnStmt = $nodeFinder->findFirst($pagesMethod, function(Node $n) {
            return $n instanceof Node\Stmt\Return_;
        });

        if (!$returnStmt || !$returnStmt->expr instanceof Node\Expr\Array_) {
            $this->log('warning', 'Return statement not found or not an array in getPages method');
            return $pages;
        }

        foreach ($returnStmt->expr->items as $item) {
            if ($item->key instanceof Node\Scalar\String_ && $item->value instanceof Node\Expr\StaticCall) {
                $pageType = $item->key->value;
                $pageClass = $item->value->class->toString();
                $pageRoute = '';
                
                if (isset($item->value->args[0]) && $item->value->args[0]->value instanceof Node\Scalar\String_) {
                    $pageRoute = $item->value->args[0]->value->value;
                }
                
                $pages[$pageType] = [
                    'class' => $pageClass,
                    'route' => $pageRoute
                ];
            } else {
                $this->log('warning', 'Unexpected structure in getPages array item: ' . $nodeDumper->dump($item));
            }
        }

        return $pages;
    }

    /**
     * Find a property in the class
     *
     * @param Node\Stmt\Class_ $node Class node
     * @param string $propertyName Property name
     * @return Node\Stmt\Property|null Property node or null if not found
     */
    protected function findProperty(Node\Stmt\Class_ $node, $propertyName)
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Property && $stmt->props[0]->name->name === $propertyName) {
                return $stmt;
            }
        }
        return null;
    }

    /**
     * Find a method in the class
     *
     * @param Node\Stmt\Class_ $node Class node
     * @param string $methodName Method name
     * @return Node\Stmt\ClassMethod|null Method node or null if not found
     */
    protected function findMethod(Node\Stmt\Class_ $node, $methodName)
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->name === $methodName) {
                return $stmt;
            }
        }
        return null;
    }

    /**
     * Format the documentation
     *
     * @return string Formatted documentation
     */
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
            foreach ($resource['pages'] as $pageType => $pageInfo) {
                $output .= "- $pageType:\n";
                $output .= "  - Class: {$pageInfo['class']}\n";
                $output .= "  - Route: {$pageInfo['route']}\n";
            }
            $output .= "\n";
        }

        return $output;
    }
}