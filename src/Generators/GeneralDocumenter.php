<?php

namespace Elalecs\LaravelDocumenter\Generators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\View;

/**
 * Class GeneralDocumenter
 * 
 * This class is responsible for generating documentation for general PHP classes in a Laravel project.
 * 
 * @package Elalecs\LaravelDocumenter\Generators
 */
class GeneralDocumenter extends BasePhpParserDocumenter
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
     * @var array
     */
    protected $documentation = [];

    /**
     * GeneralDocumenter constructor.
     * 
     * @param array $config
     */
    public function __construct($config)
    {
        parent::__construct($config);
        $this->config = $config;
        $this->setStubPath();
        $this->log('info', 'GeneralDocumenter initialized');
    }

    /**
     * Set the stub path for the general documenter
     */
    protected function setStubPath()
    {
        $stubsPath = $this->config['stubs_path'] ?? __DIR__.'/../Stubs';
        $this->stubPath = $stubsPath . "/general-documenter.blade.php";
        
        if (!File::exists($this->stubPath)) {
            throw new \RuntimeException("General documenter stub not found at {$this->stubPath}");
        }
        $this->log('info', 'Stub path set');
    }

    /**
     * Generate the documentation for general classes.
     * 
     * @return string
     */
    public function generate()
    {
        $this->log('info', 'Generating general documentation');
        $path = $this->config['path'] ?? app_path();
        $exclude = $this->config['exclude'] ?? ['Models', 'Filament'];

        $files = $this->getPhpFiles($path, $exclude);

        foreach ($files as $file) {
            $this->documentFile($file);
        }

        return $this->formatDocumentation();
    }

    /**
     * Get PHP files from the specified path, excluding certain directories.
     * 
     * @param string $path
     * @param array $exclude
     * @return \Illuminate\Support\Collection
     */
    protected function getPhpFiles($path, $exclude)
    {
        $this->log('info', 'Getting PHP files');
        return collect(File::allFiles($path))
            ->filter(function ($file) use ($exclude) {
                return $file->getExtension() === 'php' && 
                       !Str::contains($file->getPathname(), $exclude);
            });
    }

    /**
     * Document a single PHP file.
     * 
     * @param \SplFileInfo $file
     */
    protected function documentFile($file)
    {
        $this->log('info', 'Documenting file: ' . $file->getFilename());
        $ast = $this->parseFile($file->getPathname());
        $namespace = $this->extractNamespace($ast);
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

    /**
     * Get the section name based on the file path.
     * 
     * @param \SplFileInfo $file
     * @return string
     */
    protected function getSection($file)
    {
        $relativePath = Str::after($file->getPath(), app_path() . DIRECTORY_SEPARATOR);
        return explode(DIRECTORY_SEPARATOR, $relativePath)[0];
    }

    /**
     * Get the class name from the AST.
     * 
     * @param array $ast
     * @return string|null
     */
    protected function getClassName($ast)
    {
        $finder = new NodeFinder;
        $classNode = $finder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_;
        });

        return $classNode ? $classNode->name->toString() : null;
    }

    /**
     * Get the class description from the AST.
     * 
     * @param array $ast
     * @return string
     */
    protected function getClassDescription($ast)
    {
        $finder = new NodeFinder;
        $classNode = $finder->findFirst($ast, function(Node $node) {
            return $node instanceof Node\Stmt\Class_;
        });

        return $this->getDocComment($classNode);
    }

    /**
     * Get traits used by the class.
     * 
     * @param array $ast
     * @return array
     */
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

    /**
     * Get properties of the class.
     * 
     * @param array $ast
     * @return array
     */
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
                'type' => $property->type ? $this->getTypeName($property->type) : 'mixed',
                'description' => $this->getDocComment($property),
            ];
        }, $properties);
    }

    /**
     * Get methods of the class.
     * 
     * @param array $ast
     * @return array
     */
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
                'returnType' => $method->returnType ? $this->getTypeName($method->returnType) : 'void',
                'description' => $this->getDocComment($method),
            ];
        }, $methods);
    }

    /**
     * Get the access type of a class member.
     * 
     * @param Node $node
     * @return string
     */
    protected function getAccessType(Node $node)
    {
        if ($node->isPublic()) return 'public';
        if ($node->isProtected()) return 'protected';
        if ($node->isPrivate()) return 'private';
        return 'public';
    }

    /**
     * Get the parameters of a method.
     * 
     * @param Node\Stmt\ClassMethod $method
     * @return string
     */
    protected function getParameters(Node\Stmt\ClassMethod $method)
    {
        return implode(', ', array_map(function($param) {
            return ($param->type ? $this->getTypeName($param->type) . ' ' : '') . '$' . $param->var->name;
        }, $method->params));
    }

    /**
     * Format the documentation into a string.
     * 
     * @return string
     */
    protected function formatDocumentation()
    {
        $this->log('info', 'Formatting documentation');
        $output = '';
        foreach ($this->documentation as $section => $classes) {
            $output .= View::file($this->stubPath, [
                'sectionName' => $section,
                'classes' => array_map(function($class) {
                    return (object) [
                        'namespace' => Str::beforeLast(key($class), '\\'),
                        'className' => Str::afterLast(key($class), '\\'),
                        'description' => $class['description'],
                        'traits' => $class['traits'],
                        'properties' => array_map(function($prop) {
                            return (object) $prop;
                        }, $class['properties']),
                        'methods' => array_map(function($method) {
                            return (object) $method;
                        }, $class['methods']),
                    ];
                }, $classes),
            ])->render();
        }
        return $output;
    }
}