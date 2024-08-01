<?php

namespace Elalecs\LaravelDocumenter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Elalecs\LaravelDocumenter\LaravelDocumenter;
use Illuminate\Support\Str;

class GenerateDocumentation extends Command
{
    protected $signature = 'documenter:generate
                            {--component= : Specific component to document (model, filament-resource, api-controller, job, event, middleware, rule)}
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
        $component = $this->option('component');
        $output = $this->option('output');

        if ($output) {
            Config::set('laravel-documenter.output_path', $output);
        }

        $this->info('Starting documentation generation...');

        $documentation = '';
        if ($component) {
            $documentation = $this->generateForComponent($component);
        } else {
            $documentation = $this->generateAllDocumentation();
        }

        if (!empty(trim($documentation))) {
            $this->generateContributingFile($documentation);
            $this->info('Documentation generated successfully!');
            return Command::SUCCESS;
        } else {
            $this->warn('No documentation was generated.');
            return Command::SUCCESS;
        }
    }

    protected function generateForComponent($component)
    {
        $method = 'generate' . Str::studly($component) . 'Documentation';
        
        if (method_exists($this->documenter, $method)) {
            return $this->documenter->$method();
        } else {
            $this->error("Unknown component: $component");
            return '';
        }
    }

    protected function generateAllDocumentation()
    {
        $documentation = $this->documenter->generateAllDocumentation();

        return $documentation;
    }

    protected function generateContributingFile($documentation)
    {
        $stubPath = __DIR__ . '/../Stubs/contributing.stub';
        if (!File::exists($stubPath)) {
            $this->error("Contributing stub file not found at: $stubPath");
            return;
        }

        $stub = File::get($stubPath);
        $content = strtr($stub, [
            '{{generatedDocumentation}}' => $documentation,
        ]);

        $outputPath = base_path('CONTRIBUTING.md');
        File::put($outputPath, $content);
        $this->info("CONTRIBUTING.md file generated at: $outputPath");
    }
}