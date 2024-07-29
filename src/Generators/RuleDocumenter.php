<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;

/**
 * @description Class for documenting Laravel custom validation rules.
 */
class RuleDocumenter
{
    protected $config;
    protected $stubPath;

    /**
     * @description Constructor of the RuleDocumenter class.
     * @param array $config Documenter configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/rule.stub';
    }

    /**
     * @description Generates documentation for all validation rules.
     * @return string Generated documentation
     */
    public function generate()
    {
        $rules = $this->getRules();
        $documentation = '';

        foreach ($rules as $rule) {
            $documentation .= $this->documentRule($rule);
        }

        return $documentation;
    }

    /**
     * @description Gets the list of validation rules from the project.
     * @return array List of rule class names
     */
    protected function getRules()
    {
        $rulePath = $this->config['rule_path'] ?? app_path('Rules');
        $files = File::allFiles($rulePath);

        return collect($files)->map(function ($file) use ($rulePath) {
            $relativePath = $file->getRelativePath();
            $namespace = str_replace('/', '\\', $relativePath);
            $className = $file->getBasename('.php');
            return "App\\Rules\\{$namespace}\\{$className}";
        })->all();
    }

    /**
     * @description Documents an individual validation rule.
     * @param string $ruleClass Name of the rule class
     * @return string Documentation of the rule
     */
    protected function documentRule($ruleClass)
    {
        try {
            $reflection = new ReflectionClass($ruleClass);
            $stub = File::get($this->stubPath);

            return strtr($stub, [
                '{{ruleName}}' => $reflection->getShortName(),
                '{{description}}' => $this->getRuleDescription($reflection),
                '{{passesMethod}}' => $this->getPassesMethod($reflection),
                '{{message}}' => $this->getMessage($reflection),
                '{{constructorParameters}}' => $this->getConstructorParameters($reflection),
            ]);
        } catch (\ReflectionException $e) {
            // Log the error or handle it as needed
            return sprintf("Error documenting rule %s: %s\n", $ruleClass, $e->getMessage());
        }
    }

    /**
     * @description Gets the rule description from its DocBlock.
     * @param ReflectionClass $reflection Reflection of the rule class
     * @return string Description of the rule
     */
    protected function getRuleDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No description provided.';
    }

    /**
     * @description Gets information about the rule's passes method.
     * @param ReflectionClass $reflection Reflection of the rule class
     * @return string Documentation of the passes method
     */
    protected function getPassesMethod(ReflectionClass $reflection)
    {
        $passesMethod = $reflection->getMethod('passes');
        $docComment = $passesMethod->getDocComment();

        $description = 'No description provided.';
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            $description = trim($matches[1]);
        }

        return sprintf("Description: %s\n\nParameters:\n%s",
            $description,
            $this->getMethodParameters($passesMethod)
        );
    }

    /**
     * @description Gets the rule's error message.
     * @param ReflectionClass $reflection Reflection of the rule class
     * @return string Error message of the rule
     */
    protected function getMessage(ReflectionClass $reflection)
    {
        if ($reflection->hasMethod('message')) {
            $messageMethod = $reflection->getMethod('message');
            $docComment = $messageMethod->getDocComment();

            if (preg_match('/@return\s+(.+)/', $docComment, $matches)) {
                return trim($matches[1]);
            }
        }
        return 'Default Laravel validation message.';
    }

    /**
     * @description Gets the rule's constructor parameters.
     * @param ReflectionClass $reflection Reflection of the rule class
     * @return string Documentation of the constructor parameters
     */
    protected function getConstructorParameters(ReflectionClass $reflection)
    {
        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            return 'No parameters in the constructor.';
        }

        $parameters = '';
        foreach ($constructor->getParameters() as $param) {
            $parameters .= sprintf("- `%s`", $param->getName());
            if ($param->hasType()) {
                $parameters .= sprintf(" (%s)", $param->getType()->getName());
            }
            $docComment = $constructor->getDocComment();
            if (preg_match('/@param\s+\S+\s+\$' . $param->getName() . '\s+(.+)/s', $docComment, $matches)) {
                $parameters .= sprintf(": %s", trim($matches[1]));
            }
            $parameters .= "\n";
        }
        return $parameters;
    }

    /**
     * @description Gets the parameters of a method.
     * @param ReflectionMethod $method Method to analyze
     * @return string Documentation of the method parameters
     */
    protected function getMethodParameters(ReflectionMethod $method)
    {
        $parameters = '';
        foreach ($method->getParameters() as $param) {
            $parameters .= sprintf("- `%s`", $param->getName());
            if ($param->hasType()) {
                $parameters .= sprintf(" (%s)", $param->getType()->getName());
            }
            $parameters .= "\n";
        }
        return $parameters ?: 'No parameters.';
    }
}