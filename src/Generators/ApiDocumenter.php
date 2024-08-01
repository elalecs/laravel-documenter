<?php

namespace Elalecs\LaravelDocumenter\Generators;

use PhpParser\Node;
use PhpParser\NodeFinder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApiDocumenter extends BasePhpParserDocumenter
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
        $routeFiles = $this->getRouteFiles();

        foreach ($routeFiles as $file) {
            $this->documentRouteFile($file);
        }

        return $this->formatDocumentation();
    }

    protected function getRouteFiles()
    {
        $path = $this->config['path'] ?? base_path('routes');
        $files = $this->config['files'] ?? ['api.php'];

        return collect($files)->map(function ($file) use ($path) {
            return $path . '/' . $file;
        })->filter(function ($file) {
            return File::exists($file);
        });
    }

    protected function documentRouteFile($file)
    {
        $ast = $this->parseFile($file);
        $nodeFinder = new NodeFinder;

        $routeNodes = $nodeFinder->find($ast, function(Node $node) {
            return $node instanceof Node\Expr\MethodCall && 
                   $node->name->name === 'group' &&
                   $node->var->name->parts[0] === 'Route';
        });

        $fileName = basename($file);
        $this->documentation[$fileName] = [];

        foreach ($routeNodes as $routeNode) {
            $this->processRouteGroup($routeNode, $fileName);
        }
    }

    protected function processRouteGroup(Node\Expr\MethodCall $node, $fileName, $prefix = '')
    {
        $groupAttributes = $this->getGroupAttributes($node);
        $newPrefix = $prefix . ($groupAttributes['prefix'] ?? '');

        foreach ($node->args[1]->value->stmts as $stmt) {
            if ($stmt instanceof Node\Expr\MethodCall && $stmt->var->name->parts[0] === 'Route') {
                $this->processRoute($stmt, $fileName, $newPrefix, $groupAttributes['middleware'] ?? []);
            } elseif ($stmt instanceof Node\Expr\MethodCall && $stmt->name->name === 'group') {
                $this->processRouteGroup($stmt, $fileName, $newPrefix);
            }
        }
    }

    protected function getGroupAttributes(Node\Expr\MethodCall $node)
    {
        $attributes = [];
        if ($node->args[0]->value instanceof Node\Expr\Array_) {
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

    protected function processRoute(Node\Expr\MethodCall $node, $fileName, $prefix, $groupMiddleware)
    {
        $method = $node->name->name;
        $uri = $prefix . '/' . $node->args[0]->value->value;
        $action = $this->getRouteAction($node->args[1]->value);
        $middleware = array_merge($groupMiddleware, $this->getRouteMiddleware($node));

        $this->documentation[$fileName][] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'action' => $action,
            'middleware' => $middleware,
            'parameters' => $this->getActionParameters($action),
            'description' => $this->getActionDescription($action),
        ];
    }

    protected function getRouteAction($node)
    {
        if ($node instanceof Node\Expr\Array_) {
            foreach ($node->items as $item) {
                if ($item->key->value === 'uses') {
                    return $item->value->value;
                }
            }
        } elseif ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }
        return 'Closure';
    }

    protected function getRouteMiddleware(Node\Expr\MethodCall $node)
    {
        $middleware = [];
        $parent = $node->getAttribute('parent');
        if ($parent instanceof Node\Expr\MethodCall && $parent->name->name === 'middleware') {
            $middleware = $this->extractMiddleware($parent->args[0]->value);
        }
        return $middleware;
    }

    protected function extractMiddleware($node)
    {
        if ($node instanceof Node\Scalar\String_) {
            return [$node->value];
        } elseif ($node instanceof Node\Expr\Array_) {
            return array_map(function($item) {
                return $item->value->value;
            }, $node->items);
        }
        return [];
    }

    protected function getActionParameters($action)
    {
        if (Str::contains($action, '@')) {
            list($controller, $method) = explode('@', $action);
            $controllerFile = app_path('Http/Controllers/' . str_replace('\\', '/', $controller) . '.php');
            if (File::exists($controllerFile)) {
                $ast = $this->parseFile($controllerFile);
                $nodeFinder = new NodeFinder;
                $methodNode = $nodeFinder->findFirst($ast, function(Node $node) use ($method) {
                    return $node instanceof Node\Stmt\ClassMethod && $node->name->name === $method;
                });
                if ($methodNode) {
                    return array_map(function($param) {
                        return [
                            'name' => $param->var->name,
                            'type' => $param->type ? $this->getTypeName($param->type) : 'mixed',
                        ];
                    }, $methodNode->params);
                }
            }
        }
        return [];
    }

    protected function getTypeName($type)
    {
        if ($type instanceof Node\Name) {
            return $type->toString();
        } elseif ($type instanceof Node\NullableType) {
            return '?' . $this->getTypeName($type->type);
        }
        return (string) $type;
    }

    protected function getActionDescription($action)
    {
        if (Str::contains($action, '@')) {
            list($controller, $method) = explode('@', $action);
            $controllerFile = app_path('Http/Controllers/' . str_replace('\\', '/', $controller) . '.php');
            if (File::exists($controllerFile)) {
                $ast = $this->parseFile($controllerFile);
                $nodeFinder = new NodeFinder;
                $methodNode = $nodeFinder->findFirst($ast, function(Node $node) use ($method) {
                    return $node instanceof Node\Stmt\ClassMethod && $node->name->name === $method;
                });
                if ($methodNode && $methodNode->getDocComment()) {
                    $docComment = $methodNode->getDocComment()->getText();
                    if (preg_match('/@description\s+(.*)\n/s', $docComment, $matches)) {
                        return trim($matches[1]);
                    }
                }
            }
        }
        return 'No description available.';
    }

    protected function formatDocumentation()
    {
        $output = '';

        foreach ($this->documentation as $fileName => $routes) {
            $output .= "## $fileName\n\n";

            foreach ($routes as $route) {
                $output .= "### {$route['method']} {$route['uri']}\n\n";
                
                $output .= "**Handler:** {$route['action']}\n\n";
                $output .= "{$route['description']}\n\n";

                if (!empty($route['parameters'])) {
                    $output .= "**Parameters:**\n";
                    foreach ($route['parameters'] as $param) {
                        $output .= "- {$param['name']} ({$param['type']})\n";
                    }
                    $output .= "\n";
                }

                if (!empty($route['middleware'])) {
                    $output .= "**Middleware:**\n";
                    foreach ($route['middleware'] as $middleware) {
                        $output .= "- $middleware\n";
                    }
                    $output .= "\n";
                }

                $output .= "\n";
            }
        }

        return $output;
    }
}