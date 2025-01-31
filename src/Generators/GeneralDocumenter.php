<?php

/**
 * This file contains the GeneralDocumenter class which is responsible for generating
 * documentation for general PHP classes in a Laravel project.
 */

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
 * It extends BasePhpParserDocumenter and provides methods to parse, analyze, and document PHP files.
 * 
 * @package Elalecs\LaravelDocumenter\Generators
 */
class GeneralDocumenter extends BasePhpParserDocumenter
{
    /**
     * Configuration array for the documenter.
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
     * Array to store the generated documentation.
     *
     * @var array
     */
    protected $documentation = [];

    /**
     * GeneralDocumenter constructor.
     * 
     * @param array $config Configuration array for the documenter
     */
    public function __construct($config)
    {
        parent::__construct($config);
        $this->config = $config;
        $this->setStubPath();
        $this->log('info', 'GeneralDocumenter initialized');
    }

    /**
     * Set the stub path for the general documenter.
     *
     * @throws \RuntimeException If the stub file is not found
     * @return void
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
     * @return string The generated documentation as a string
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
     * @param string $path The base path to search for PHP files
     * @param array $exclude An array of directory names to exclude
     * @return \Illuminate\Support\Collection A collection of SplFileInfo objects
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
     * @param \SplFileInfo $file The file to document
     * @return void
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
     * @param \SplFileInfo $file The file object
     * @return string The section name
     */
    protected function getSection($file)
    {
        $relativePath = Str::after($file->getPath(), app_path() . DIRECTORY_SEPARATOR);
        return explode(DIRECTORY_SEPARATOR, $relativePath)[0];
    }

    /**
     * Get the class name from the AST.
     * 
     * @param array $ast The Abstract Syntax Tree
     * @return string|null The class name if found, null otherwise
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
     * Get the description from the docblock of a node.
     *
     * @param Node $node The node to get the description from
     * @return string The description if found, otherwise a default message
     */
    protected function getDocComment(Node $node)
    {
        if ($node->getDocComment()) {
            $docComment = $node->getDocComment()->getText();
            
            // Remove the opening and closing tags of the doc comment
            $docComment = preg_replace('/^\/\*\*|\*\/$/s', '', $docComment);
            
            // Remove asterisks at the beginning of each line
            $docComment = preg_replace('/^\s*\*\s?/m', '', $docComment);
            
            // Remove the class name line if it exists
            $docComment = preg_replace('/^(Class|Interface|Trait)\s+\w+\s*$/m', '', $docComment);
            
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

    /**
     * Get the class description from the AST.
     * 
     * @param array $ast The Abstract Syntax Tree
     * @return string The class description
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
     * @param array $ast The Abstract Syntax Tree
     * @return array An array of trait names
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
     * @param array $ast The Abstract Syntax Tree
     * @return array An array of property information
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
     * @param array $ast The Abstract Syntax Tree
     * @return array An array of method information
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
     * @param Node $node The node representing a class member
     * @return string The access type (public, protected, or private)
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
     * @param Node\Stmt\ClassMethod $method The method node
     * @return string A string representation of the method parameters
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
     * @return string The formatted documentation
     */
    protected function formatDocumentation()
    {
        $this->log('info', 'Formatting documentation');
        $output = '';
        foreach ($this->documentation as $section => $classes) {
            if (!empty($classes)) {
                $formattedClasses = array_map(function($fullClassName, $class) {
                    return (object) [
                        'namespace' => Str::beforeLast($fullClassName, '\\'),
                        'className' => Str::afterLast($fullClassName, '\\'),
                        'description' => $class['description'],
                        'traits' => $class['traits'],
                        'properties' => array_map(function($prop) {
                            return (object) $prop;
                        }, $class['properties']),
                        'methods' => array_map(function($method) {
                            return (object) $method;
                        }, $class['methods']),
                    ];
                }, array_keys($classes), $classes);

                $output .= View::file($this->stubPath, [
                    'sectionName' => $section,
                    'classes' => $formattedClasses,
                ])->render();
            }
        }
        return $output;
    }
}