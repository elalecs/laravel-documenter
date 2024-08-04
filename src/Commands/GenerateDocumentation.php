<?php

namespace Elalecs\LaravelDocumenter\Commands;

use Illuminate\Console\Command;
use Elalecs\LaravelDocumenter\LaravelDocumenter;

class GenerateDocumentation extends Command
{
    protected $signature = 'documenter:generate
                            {--type= : Specific type of documentation to generate (general, model, api, filament)}
                            {--output= : Custom output path for the documentation}';

    protected $description = 'Generate documentation for your Laravel and Filament project';

    protected $documenter;

    public function __construct(LaravelDocumenter $documenter)
    {
        parent::__construct();
        $this->documenter = $documenter;
    }

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

    protected function generateContributing($documentation)
    {
        $this->documenter->generateContributingFile($documentation);
        $outputPath = config('laravel-documenter.output_path');
        $this->info("Documentation saved to: $outputPath");
    }
}