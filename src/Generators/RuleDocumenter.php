<?php

/**
 * @description Clase para documentar reglas de validación personalizadas de Laravel.
 */
class RuleDocumenter
{
    protected $config;
    protected $stubPath;

    /**
     * @description Constructor de la clase RuleDocumenter.
     * @param array $config Configuración del documentador
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/rule.stub';
    }

    /**
     * @description Genera la documentación para todas las reglas de validación.
     * @return string Documentación generada
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
     * @description Obtiene la lista de reglas de validación del proyecto.
     * @return array Lista de nombres de clase de reglas
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
     * @description Documenta una regla de validación individual.
     * @param string $ruleClass Nombre de la clase de la regla
     * @return string Documentación de la regla
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
            // Registrar el error o manejarlo según sea necesario
            return sprintf("Error al documentar la regla %s: %s\n", $ruleClass, $e->getMessage());
        }
    }

    /**
     * @description Obtiene la descripción de la regla desde su DocBlock.
     * @param ReflectionClass $reflection Reflexión de la clase de la regla
     * @return string Descripción de la regla
     */
    protected function getRuleDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No se proporcionó descripción.';
    }

    /**
     * @description Obtiene información sobre el método passes de la regla.
     * @param ReflectionClass $reflection Reflexión de la clase de la regla
     * @return string Documentación del método passes
     */
    protected function getPassesMethod(ReflectionClass $reflection)
    {
        $passesMethod = $reflection->getMethod('passes');
        $docComment = $passesMethod->getDocComment();

        $description = 'No se proporcionó descripción.';
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            $description = trim($matches[1]);
        }

        return sprintf("Descripción: %s\n\nParámetros:\n%s",
            $description,
            $this->getMethodParameters($passesMethod)
        );
    }

    /**
     * @description Obtiene el mensaje de error de la regla.
     * @param ReflectionClass $reflection Reflexión de la clase de la regla
     * @return string Mensaje de error de la regla
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
        return 'Mensaje de validación predeterminado de Laravel.';
    }

    /**
     * @description Obtiene los parámetros del constructor de la regla.
     * @param ReflectionClass $reflection Reflexión de la clase de la regla
     * @return string Documentación de los parámetros del constructor
     */
    protected function getConstructorParameters(ReflectionClass $reflection)
    {
        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            return 'No hay parámetros en el constructor.';
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
     * @description Obtiene los parámetros de un método.
     * @param ReflectionMethod $method Método a analizar
     * @return string Documentación de los parámetros del método
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
        return $parameters ?: 'No parámetros.';
    }
}