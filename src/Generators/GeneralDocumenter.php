<?php

namespace Elalecs\LaravelDocumenter\Generators;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Node;
use PhpParser\NodeFinder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GeneralDocumenter extends BasePhpParserDocumenter
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
        $path = $this->config['path'] ?? app_path();
        $exclude = $this->config['exclude'] ?? ['Models', 'Filament'];

        $files = $this->getPhpFiles($path, $exclude);

        foreach ($files as $file) {
            $this->documentFile($file);
        }

        return $this->formatDocumentation();
    }

    protected function getPhpFiles($path, $exclude)
    {
        return collect(File::allFiles($path))
            ->filter(function ($file) use ($exclude) {
                return $file->getExtension() === 'php' && 
                       !Str::contains($file->getPathname(), $exclude);
            });
    }

    protected function documentFile($file)
    {
        $ast = $this->parseFile($file->getPathname());
        $namespace = $this->getNamespace($ast);
        $className = $this->getClassName($ast);

        if ($namespace && $className) {
            $section = $this->getSection($file);
            $fullClassName = $namespace . '\\' . $className;

            $this->documentation[$section][$fullClassName] = [
                'description' => $this->getClassDescription($ast),
                'traits' => $this->getTraits($ast),
                'properties' => $this->getProperties($ast),
                'methods' => $this->getMethods($ast),
            ];
        }
    }

    protected function getSection($file)
    {
        $relativePath = Str::after($file->getPath(), app_path() . DIRECTORY_SEPARATOR);
        return explode(DIRECTORY_SEPARATOR, $relativePath)[0];
    }

    protected function getNamespace($ast)
    {
        $finder = new NodeFinder;
        $namespaceNode = $finder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Namespace_;
        });

        return $namespaceNode ? $namespaceNode->name->toString() : null;
    }

    protected function getClassName($ast)
    {
        $finder = new NodeFinder;
        $classNode = $finder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_;
        });

        return $classNode ? $classNode->name->toString() : null;
    }

    protected function getClassDescription($ast)
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

    protected function getTraits($ast)
    {
        $finder = new NodeFinder;
        $traits = $finder->find($ast, function(Node $node) {
            return $node instanceof Node\Stmt\TraitUse;
        });

        return array_map(function($trait) {
            return $trait->traits[0]->toString();
        }, $traits);
    }

    protected function getProperties($ast)
    {
        $finder = new NodeFinder;
        $properties = $finder->find($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Property;
        });

        return array_map(function($property) {
            return [
                'name' => $property->props[0]->name->toString(),
                'accessType' => $this->getAccessType($property),
                'type' => $property->type ? $property->type->toString() : 'mixed',
                'description' => $this->getDocComment($property),
            ];
        }, $properties);
    }

    protected function getMethods($ast)
    {
        $finder = new NodeFinder;
        $methods = $finder->find($ast, function(Node $node) {
            return $node instanceof Node\Stmt\ClassMethod;
        });

        return array_map(function($method) {
            return [
                'name' => $method->name->toString(),
                'accessType' => $this->getAccessType($method),
                'parameters' => $this->getParameters($method),
                'returnType' => $method->returnType ? $method->returnType->toString() : 'void',
                'description' => $this->getDocComment($method),
            ];
        }, $methods);
    }

    protected function getAccessType(Node $node)
    {
        if ($node->isPublic()) return 'public';
        if ($node->isProtected()) return 'protected';
        if ($node->isPrivate()) return 'private';
        return 'public';
    }

    protected function getParameters(Node\Stmt\ClassMethod $method)
    {
        return implode(', ', array_map(function($param) {
            return ($param->type ? $param->type->toString() . ' ' : '') . '$' . $param->var->name;
        }, $method->params));
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

    protected function formatDocumentation()
    {
        $output = '';
        foreach ($this->documentation as $section => $classes) {
            $output .= "## $section\n\n";
            foreach ($classes as $className => $classInfo) {
                $output .= "### $className\n\n";
                $output .= $classInfo['description'] . "\n\n";
                
                if (!empty($classInfo['traits'])) {
                    $output .= "**Traits:**\n";
                    foreach ($classInfo['traits'] as $trait) {
                        $output .= "- $trait\n";
                    }
                    $output .= "\n";
                }

                if (!empty($classInfo['properties'])) {
                    $output .= "**Properties:**\n";
                    foreach ($classInfo['properties'] as $property) {
                        $output .= "- {$property['accessType']} {$property['name']}: {$property['type']}\n";
                        $output .= "  {$property['description']}\n";
                    }
                    $output .= "\n";
                }

                $output .= "**Methods:**\n";
                foreach ($classInfo['methods'] as $method) {
                    $output .= "- {$method['accessType']} {$method['name']}({$method['parameters']}): {$method['returnType']}\n";
                    $output .= "  {$method['description']}\n";
                }
                $output .= "\n";
            }
        }
        return $output;
    }
}