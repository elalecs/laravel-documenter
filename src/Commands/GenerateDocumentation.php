<?php

/**
 * This file contains the GenerateDocumentation command class for the Laravel Documenter package.
 * It provides functionality to generate various types of documentation for Laravel and Filament projects.
 */

namespace Elalecs\LaravelDocumenter\Commands;

use Illuminate\Console\Command;
use Elalecs\LaravelDocumenter\LaravelDocumenter;
use Illuminate\Support\Facades\File;

/**
 * Class GenerateDocumentation
 *
 * This command class is responsible for generating documentation for Laravel and Filament projects.
 * It allows users to generate specific types of documentation or all types at once.
 */
class GenerateDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documenter:generate
                            {--type= : Specific type of documentation to generate (general, model, api, filament)}
                            {--output= : Custom output path for the documentation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate documentation for your Laravel and Filament project';

    /**
     * The LaravelDocumenter instance.
     *
     * @var \Elalecs\LaravelDocumenter\LaravelDocumenter
     */
    protected $documenter;

    /**
     * Create a new command instance.
     *
     * @param \Elalecs\LaravelDocumenter\LaravelDocumenter $documenter The LaravelDocumenter instance
     * @return void
     */
    public function __construct(LaravelDocumenter $documenter)
    {
        parent::__construct();
        $this->documenter = $documenter;
    }

    /**
     * Execute the console command.
     *
     * This method handles the execution of the command, generating either specific
     * documentation types or all types based on the provided options.
     *
     * @return int
     */
    public function handle()
    {
        $type = $this->option('type');
        $output = $this->option('output');

        if ($output) {
            config(["laravel-documenter.output_path.{$type}" => $output]);
        }

        $this->info('Starting documentation generation...');

        if ($type) {
            $this->generateSpecificDocumentation($type);
        } else {
            $this->documenter->generate();
        }

        $this->info('Documentation generated successfully!');

        return Command::SUCCESS;
    }

    /**
     * Generate specific type of documentation.
     *
     * This method generates documentation for a specific type and saves it to the configured output path.
     *
     * @param string $type The type of documentation to generate
     * @return void
     * @throws \RuntimeException If the specified documentation type is unknown
     */
    protected function generateSpecificDocumentation($type)
    {
        $method = 'generate' . ucfirst($type) . 'Documentation';
        
        if (method_exists($this->documenter, $method)) {
            $documentation = $this->documenter->$method();
            $outputPath = config("laravel-documenter.output_path.{$type}");
            File::put($outputPath, $documentation);
            $this->info("Documentation for {$type} saved to: {$outputPath}");
        } else {
            $this->error("Unknown documentation type: $type");
        }
    }

    /**
     * Generate contributing file.
     *
     * This method generates a contributing file and saves it to the configured output path.
     *
     * @param string $documentation The content of the contributing file
     * @return void
     */
    protected function generateContributing($documentation)
    {
        $this->documenter->generateContributingFile($documentation);
        $outputPath = config('laravel-documenter.output_path');
        $this->info("Documentation saved to: $outputPath");
    }
}