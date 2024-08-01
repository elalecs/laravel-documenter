<?php

namespace Elalecs\LaravelDocumenter\Generators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FilamentDocumenter extends BasePhpParserDocumenter
{
    protected $config;
    protected $documentation = [];

    public function __construct($config)
    {
        parent::__construct();
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
        $this->documentation[$resourceName] = [
            'description' => $this->getClassDescription($classNode),
            'model' => $this->getAssociatedModel($classNode),
            'pages' => $this->getPages($classNode),
            'tableColumns' => $this->getTableColumns($classNode),
            'formFields' => $this->getFormFields($classNode),
            'actions' => $this->getActions($classNode),
            'filters' => $this->getFilters($classNode),
        ];
    }

    protected function extendsFilamentResource(Node\Stmt\Class_ $node)
    {
        if ($node->extends) {
            return $node->extends->toString() === 'Filament\Resources\Resource';
        }
        return false;
    }

    protected function getClassDescription(Node\Stmt\Class_ $node)
    {
        $docComment = $node->getDocComment();
        if ($docComment) {
            if (preg_match('/@description\s+(.*)\n/s', $docComment->getText(), $matches)) {
                return trim($matches[1]);
            }
        }
        return 'No description available.';
    }

    protected function getAssociatedModel(Node\Stmt\Class_ $node)
    {
        $modelMethod = $this->findMethod($node, 'getModelLabel');
        if ($modelMethod) {
            // This is a simplification. In reality, you'd need to evaluate the method body
            // to get the actual model class name.
            return 'Derived from getModelLabel() method';
        }
        return 'Unknown';
    }

    protected function getPages(Node\Stmt\Class_ $node)
    {
        $pages = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && Str::endsWith($stmt->name->toString(), 'Page')) {
                $pages[] = [
                    'name' => Str::beforeLast($stmt->name->toString(), 'Page'),
                    'class' => 'Derived from ' . $stmt->name->toString() . ' method',
                ];
            }
        }
        return $pages;
    }

    protected function getTableColumns(Node\Stmt\Class_ $node)
    {
        $tableMethod = $this->findMethod($node, 'table');
        if ($tableMethod) {
            // This is a placeholder. You'd need to analyze the method body
            // to extract the actual table columns.
            return ['Columns derived from table() method'];
        }
        return [];
    }

    protected function getFormFields(Node\Stmt\Class_ $node)
    {
        $formMethod = $this->findMethod($node, 'form');
        if ($formMethod) {
            // This is a placeholder. You'd need to analyze the method body
            // to extract the actual form fields.
            return ['Fields derived from form() method'];
        }
        return [];
    }

    protected function getActions(Node\Stmt\Class_ $node)
    {
        $actionsMethod = $this->findMethod($node, 'getActions');
        if ($actionsMethod) {
            // This is a placeholder. You'd need to analyze the method body
            // to extract the actual actions.
            return ['Actions derived from getActions() method'];
        }
        return [];
    }

    protected function getFilters(Node\Stmt\Class_ $node)
    {
        $filtersMethod = $this->findMethod($node, 'getFilters');
        if ($filtersMethod) {
            // This is a placeholder. You'd need to analyze the method body
            // to extract the actual filters.
            return ['Filters derived from getFilters() method'];
        }
        return [];
    }

    protected function findMethod(Node\Stmt\Class_ $node, $methodName)
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === $methodName) {
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
            $output .= "{$resource['description']}\n\n";
            $output .= "**Model:** {$resource['model']}\n\n";

            if (!empty($resource['pages'])) {
                $output .= "**Pages:**\n";
                foreach ($resource['pages'] as $page) {
                    $output .= "- {$page['name']}: {$page['class']}\n";
                }
                $output .= "\n";
            }

            if (!empty($resource['tableColumns'])) {
                $output .= "**Table Columns:**\n";
                foreach ($resource['tableColumns'] as $column) {
                    $output .= "- $column\n";
                }
                $output .= "\n";
            }

            if (!empty($resource['formFields'])) {
                $output .= "**Form Fields:**\n";
                foreach ($resource['formFields'] as $field) {
                    $output .= "- $field\n";
                }
                $output .= "\n";
            }

            if (!empty($resource['actions'])) {
                $output .= "**Actions:**\n";
                foreach ($resource['actions'] as $action) {
                    $output .= "- $action\n";
                }
                $output .= "\n";
            }

            if (!empty($resource['filters'])) {
                $output .= "**Filters:**\n";
                foreach ($resource['filters'] as $filter) {
                    $output .= "- $filter\n";
                }
                $output .= "\n";
            }

            $output .= "\n";
        }

        return $output;
    }
}