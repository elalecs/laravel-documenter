<?php

namespace Elalecs\LaravelDocumenter\Generators;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\PhpVersion;

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
     * Create a new BasePhpParserDocumenter instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();

        $this->traverser = new NodeTraverser();

        $this->traverser->addVisitor(new NameResolver());
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
}