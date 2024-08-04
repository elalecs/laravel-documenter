<?php

namespace Elalecs\LaravelDocumenter\Generators;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Namespace_;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Base class for PHP Parser based documenters.
 * 
 * This abstract class provides common functionality for documenters that use PHP Parser
 * to parse and traverse PHP code. It sets up the parser and traverser, and provides a
 * method to parse a file and traverse its Abstract Syntax Tree (AST).
 */
abstract class BasePhpParserDocumenter
{
    /**
     * The PHP Parser instance.
     *
     * @var \PhpParser\Parser
     */
    protected $parser;

    /**
     * The Node Traverser instance.
     *
     * @var \PhpParser\NodeTraverser
     */
    protected $traverser;

    /**
     * The configuration array.
     *
     * @var array
     */
    protected $config;

    /**
     * Create a new BasePhpParserDocumenter instance.
     *
     * @param array $config The configuration array
     * @return void
     */
    public function __construct($config)
    {
        $this->config = $config;

        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();

        $this->traverser = new NodeTraverser();

        $this->traverser->addVisitor(new NameResolver());
    }

    /**
     * Log a message if logging is enabled and the log level is appropriate.
     *
     * @param string $level The log level (e.g., 'info', 'warning')
     * @param string $message The message to log
     * @return void
     */
    protected function log($level, $message)
    {
        if (isset($this->config['logging']['enabled']) && $this->config['logging']['enabled']) {
            $logLevel = $this->config['logging']['level'] ?? 'warning';
            if ($this->shouldLog($level, $logLevel)) {
                Log::$level($message);
            }
        }
    }

    /**
     * Determine if a message should be logged based on the configured log level.
     *
     * @param string $messageLevel The level of the message to log
     * @param string $configLevel The configured log level
     * @return bool True if the message should be logged, false otherwise
     */
    protected function shouldLog($messageLevel, $configLevel)
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        return $levels[$messageLevel] >= $levels[$configLevel];
    }

    /**
     * Parse a PHP file and traverse its AST.
     *
     * @param string $filePath The path to the PHP file to parse
     * @return array The traversed AST nodes
     */
    protected function parseFile($filePath)
    {
        // Read the contents of the file
        $code = file_get_contents($filePath);

        // Parse the code into an AST
        $ast = $this->parser->parse($code);

        // Traverse the AST and return the modified nodes
        return $this->traverser->traverse($ast);
    }

    /**
     * Generate the documentation.
     *
     * @return mixed
     */
    abstract public function generate();

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
            if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
                return trim($matches[1]);
            }
        }
        return 'No description provided.';
    }

    /**
     * Convert an array of nodes to an array of strings.
     *
     * @param array $array The array to convert
     * @return array The converted array
     */
    protected function convertToStringArray($array)
    {
        return array_map(function ($item) {
            return $item instanceof String_ ? $item->value : (string)$item;
        }, $array);
    }

    /**
     * Extract the namespace from an AST.
     *
     * @param array $ast The AST to extract the namespace from
     * @return string|null The namespace if found, otherwise null
     */
    protected function extractNamespace($ast)
    {
        $nodeFinder = new NodeFinder;
        $namespaceNode = $nodeFinder->findFirst($ast, function(Node $node) {
            return $node instanceof Namespace_;
        });

        return $namespaceNode ? $namespaceNode->name->toString() : null;
    }

    /**
     * Get the related model from a node.
     *
     * @param Node $node The node to get the related model from
     * @return string The related model if found, otherwise 'Unknown'
     */
    protected function getRelatedModel($node)
    {
        if ($node instanceof ClassConstFetch) {
            return $node->class->toString() . '::class';
        } elseif ($node instanceof String_) {
            return $node->value;
        } else {
            return 'Unknown';
        }
    }

    /**
     * Get the attributes of a route group.
     *
     * @param MethodCall $node The route group node
     * @return array The group attributes
     */
    protected function getGroupAttributes(MethodCall $node)
    {
        $attributes = [];
        if ($node->args[0]->value instanceof Array_) {
            foreach ($node->args[0]->value->items as $item) {
                if ($item->key->value === 'prefix') {
                    $attributes['prefix'] = $item->value->value;
                } elseif ($item->key->value === 'middleware') {
                    $attributes['middleware'] = $this->extractMiddleware($item->value);
                }
            }
        }
        return $attributes;
    }

    /**
     * Extract middleware from a node.
     *
     * @param Node $node The node to extract middleware from
     * @return array The middleware array
     */
    protected function extractMiddleware($node)
    {
        if ($node instanceof String_) {
            return [$node->value];
        } elseif ($node instanceof Array_) {
            return array_map(function($item) {
                return $item->value->value;
            }, $node->items);
        }
        return [];
    }

    /**
     * Get the action of a route.
     *
     * @param Node $node The route node
     * @return string The route action
     */
    protected function getRouteAction($node)
    {
        if ($node instanceof Node\Expr\Array_) {
            foreach ($node->items as $item) {
                if ($item->key instanceof Node\Scalar\String_ && $item->key->value === 'uses') {
                    if ($item->value instanceof Node\Scalar\String_) {
                        return $item->value->value;
                    } elseif ($item->value instanceof Node\Expr\ClassConstFetch) {
                        return $item->value->class->toString() . '::class';
                    }
                }
            }
        } elseif ($node instanceof Node\Scalar\String_) {
            return $node->value;
        } elseif ($node instanceof Node\Expr\ClassConstFetch) {
            return $node->class->toString() . '::class';
        }
        
        return 'Closure';
    }

    /**
     * Get the name of a type.
     *
     * @param Node $type The type node
     * @return string The type name
     */
    protected function getTypeName($type)
    {
        if ($type instanceof Name) {
            return $type->toString();
        } elseif ($type instanceof NullableType) {
            return '?' . $this->getTypeName($type->type);
        } elseif ($type instanceof UnionType) {
            return implode('|', array_map([$this, 'getTypeName'], $type->types));
        }
        return (string) $type;
    }

    /**
     * Get the full path to a stub file.
     *
     * @param string $stub The name of the stub file
     * @return string The full path to the stub file
     * @throws \RuntimeException If the stub file is not found
     */
    protected function getStubPath($stub)
    {
        $stubsPath = $this->config['stubs_path'] ?? __DIR__.'/../Stubs';
        $fullPath = $stubsPath . "/{$stub}.blade.php";
        
        if (!File::exists($fullPath)) {
            throw new \RuntimeException("Stub not found at {$fullPath}");
        }
        
        return $fullPath;
    }
}