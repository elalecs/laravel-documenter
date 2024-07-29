<?php

namespace Elalecs\LaravelDocumenter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Elalecs\LaravelDocumenter\LaravelDocumenter;
use Illuminate\Support\Str;

/**
 * Class GenerateDocumentation
 *
 * This command generates documentation for Laravel and Filament projects.
 */
class GenerateDocumentation extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'documenter:generate
                            {--component= : Specific component to document (model, filament-resource, api-controller, job, event, middleware, rule)}
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
     * @var LaravelDocumenter
     */
    protected $documenter;

    /**
     * Create a new command instance.
     *
     * @param LaravelDocumenter $documenter
     */
    public function __construct(LaravelDocumenter $documenter)
    {
        parent::__construct();
        $this->documenter = $documenter;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
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

        $this->generateContributingFile($documentation);

        $this->info('Documentation generated successfully!');
        return Command::SUCCESS;
    }

    /**
     * Generate documentation for a specific component.
     *
     * @param string $component
     * @return string
     */
    protected function generateForComponent($component)
    {
        $method = 'generate' . Str::studly($component) . 'Documentation';
        
        if (method_exists($this->documenter, $method)) {
            $documentation = $this->documenter->$method();
            $this->info(ucfirst($component) . ' documentation generated.');
            return $documentation;
        } else {
            $this->error("Unknown component: $component");
            return '';
        }
    }

    /**
     * Generate documentation for all components.
     *
     * @return string
     */
    protected function generateAllDocumentation()
    {
        $components = [
            'model',
            'filament-resource',
            'api-controller',
            'job',
            'event',
            'middleware',
            'rule'
        ];

        $documentation = '';
        foreach ($components as $component) {
            $documentation .= $this->generateForComponent($component);
        }

        return $documentation;
    }

    /**
     * Generate the CONTRIBUTING.md file.
     *
     * @param string $documentation
     * @return void
     */
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