<?php

namespace Elalecs\LaravelDocumenter\Generators;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;

/**
 * Class for documenting Laravel jobs.
 * 
 * @description This class provides functionality to generate documentation for Laravel jobs.
 */
class JobDocumenter
{
    /**
     * @var array The configuration array for the documenter.
     */
    protected $config;

    /**
     * @var string The path to the stub file for jobs.
     */
    protected $stubPath;

    /**
     * Constructor of the JobDocumenter class.
     *
     * @param array $config Documenter configuration
     * @description Initializes a new instance of the JobDocumenter class with the given configuration.
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->stubPath = __DIR__ . '/../Stubs/job.stub';
    }

    /**
     * Generates documentation for all jobs.
     *
     * @return string Generated documentation
     * @description Iterates through all jobs and generates documentation for each one.
     */
    public function generate()
    {
        $jobs = $this->getJobs();
        $documentation = '';

        foreach ($jobs as $job) {
            $documentation .= $this->documentJob($job);
        }

        return $documentation;
    }

    /**
     * Gets the list of jobs from the project.
     *
     * @return array List of job class names
     * @description Retrieves all job classes from the configured job path.
     */
    protected function getJobs()
    {
        $jobPath = $this->config['job_path'] ?? app_path('Jobs');
        $files = File::allFiles($jobPath);

        return collect($files)->map(function ($file) use ($jobPath) {
            $relativePath = $file->getRelativePath();
            $namespace = str_replace('/', '\\', $relativePath);
            $className = $file->getBasename('.php');
            return "App\\Jobs\\{$namespace}\\{$className}";
        })->all();
    }

    /**
     * Documents an individual job.
     *
     * @param string $jobClass Name of the job class
     * @return string Job documentation
     * @description Generates documentation for a single job class.
     */
    protected function documentJob($jobClass)
    {
        try {
            $reflection = new ReflectionClass($jobClass);
            $stub = File::get($this->stubPath);

            return strtr($stub, [
                '{{jobName}}' => $reflection->getShortName(),
                '{{queue}}' => $this->getJobQueue($reflection),
                '{{description}}' => $this->getJobDescription($reflection),
                '{{parameters}}' => $this->getConstructorParameters($reflection),
                '{{handleMethod}}' => $this->getHandleMethod($reflection),
                '{{implementedInterfaces}}' => $this->getImplementedInterfaces($reflection),
            ]);
        } catch (\ReflectionException $e) {
            // Log the error or handle it as needed
            return sprintf("Error documenting job %s: %s\n", $jobClass, $e->getMessage());
        }
    }

    /**
     * Gets the execution queue of the job.
     *
     * @param ReflectionClass $reflection Reflection of the job class
     * @return string Execution queue of the job
     * @description Retrieves the queue property value of the job if it exists.
     */
    protected function getJobQueue(ReflectionClass $reflection)
    {
        if ($reflection->hasProperty('queue')) {
            $queueProperty = $reflection->getProperty('queue');
            $queueProperty->setAccessible(true);
            return $queueProperty->getValue($reflection->newInstanceWithoutConstructor());
        }
        return 'default';
    }

    /**
     * Gets the job description from its DocBlock.
     *
     * @param ReflectionClass $reflection Reflection of the job class
     * @return string Job description
     * @description Extracts the description from the job class's DocBlock.
     */
    protected function getJobDescription(ReflectionClass $reflection)
    {
        $docComment = $reflection->getDocComment();
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }
        return 'No description provided.';
    }

    /**
     * Gets the constructor parameters of the job.
     *
     * @param ReflectionClass $reflection Reflection of the job class
     * @return string Documentation of the constructor parameters
     * @description Generates documentation for the job's constructor parameters.
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
     * Gets information about the job's handle method.
     *
     * @param ReflectionClass $reflection Reflection of the job class
     * @return string Documentation of the handle method
     * @description Generates documentation for the job's handle method.
     */
    protected function getHandleMethod(ReflectionClass $reflection)
    {
        $handleMethod = $reflection->getMethod('handle');
        $docComment = $handleMethod->getDocComment();

        $description = 'No description provided.';
        if (preg_match('/@description\s+(.+)/s', $docComment, $matches)) {
            $description = trim($matches[1]);
        }

        return sprintf("Description: %s\n\nParameters:\n%s",
            $description,
            $this->getMethodParameters($handleMethod)
        );
    }

    /**
     * Gets the parameters of a method.
     *
     * @param ReflectionMethod $method Method to get parameters from
     * @return string Documentation of the parameters
     * @description Generates documentation for a method's parameters.
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

    /**
     * Gets information about the interfaces implemented by the job.
     *
     * @param ReflectionClass $reflection Reflection of the job class
     * @return string Information about implemented interfaces
     * @description Retrieves and formats information about interfaces implemented by the job.
     */
    protected function getImplementedInterfaces(ReflectionClass $reflection)
    {
        $interfaces = $reflection->getInterfaceNames();
        if (empty($interfaces)) {
            return "Does not implement specific interfaces.\n";
        }

        $info = "Implements the following interfaces:\n";
        foreach ($interfaces as $interface) {
            $info .= "- " . $interface . "\n";
        }
        return $info;
    }
}