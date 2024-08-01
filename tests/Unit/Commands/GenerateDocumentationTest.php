<?php

namespace Tests\Feature;

use Elalecs\LaravelDocumenter\Commands\GenerateDocumentation;
use Elalecs\LaravelDocumenter\LaravelDocumenter;
use Illuminate\Support\Facades\File;
use Mockery;
use Orchestra\Testbench\TestCase;

class GenerateDocumentationCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the File facade
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn('{{generatedDocumentation}}');
        File::shouldReceive('put')->andReturn(true);
    }

    protected function getPackageProviders($app)
    {
        return ['Elalecs\LaravelDocumenter\LaravelDocumenterServiceProvider'];
    }

    public function testGenerateDocumentationCommand()
    {
        // Mock the LaravelDocumenter class
        $documenterMock = Mockery::mock(LaravelDocumenter::class);
        $documenterMock->shouldReceive('generateModelDocumentation')->andReturn("Model documentation\n");
        $documenterMock->shouldReceive('generateFilamentResourceDocumentation')->andReturn("Filament Resource documentation\n");
        $documenterMock->shouldReceive('generateApiControllerDocumentation')->andReturn("API Controller documentation\n");
        $documenterMock->shouldReceive('generateJobDocumentation')->andReturn("Job documentation\n");
        $documenterMock->shouldReceive('generateEventDocumentation')->andReturn("Event documentation\n");
        $documenterMock->shouldReceive('generateMiddlewareDocumentation')->andReturn("Middleware documentation\n");
        $documenterMock->shouldReceive('generateRuleDocumentation')->andReturn("Rule documentation\n");

        $this->app->instance(LaravelDocumenter::class, $documenterMock);

        // Execute the command
        $this->artisan('documenter:generate')
             ->assertSuccessful();

        // Verify that File::put was called with the expected content
        File::shouldHaveReceived('put')->withArgs(function($path, $content) {
            return $path === base_path('CONTRIBUTING.md') &&
                   strpos($content, 'Model documentation') !== false &&
                   strpos($content, 'Filament Resource documentation') !== false &&
                   strpos($content, 'API Controller documentation') !== false &&
                   strpos($content, 'Job documentation') !== false &&
                   strpos($content, 'Event documentation') !== false &&
                   strpos($content, 'Middleware documentation') !== false &&
                   strpos($content, 'Rule documentation') !== false;
        });
    }

    public function testGenerateDocumentationForSpecificComponent()
    {
        // Mock the LaravelDocumenter class
        $documenterMock = Mockery::mock(LaravelDocumenter::class);
        $documenterMock->shouldReceive('generateModelDocumentation')->andReturn("Model documentation\n");

        $this->app->instance(LaravelDocumenter::class, $documenterMock);

        // Execute the command for a specific component
        $this->artisan('documenter:generate', ['--component' => 'model'])
             ->assertSuccessful();

        // Verify that File::put was called with the expected content
        File::shouldHaveReceived('put')->withArgs(function($path, $content) {
            return $path === base_path('CONTRIBUTING.md') &&
                   strpos($content, 'Model documentation') !== false;
        });
    }

    public function testGenerateDocumentationWithNoContent()
    {
        // Mock the LaravelDocumenter class to return empty strings
        $documenterMock = Mockery::mock(LaravelDocumenter::class);
        $documenterMock->shouldReceive('generateModelDocumentation')->andReturn("");
        $documenterMock->shouldReceive('generateFilamentResourceDocumentation')->andReturn("");
        $documenterMock->shouldReceive('generateApiControllerDocumentation')->andReturn("");
        $documenterMock->shouldReceive('generateJobDocumentation')->andReturn("");
        $documenterMock->shouldReceive('generateEventDocumentation')->andReturn("");
        $documenterMock->shouldReceive('generateMiddlewareDocumentation')->andReturn("");
        $documenterMock->shouldReceive('generateRuleDocumentation')->andReturn("");

        $this->app->instance(LaravelDocumenter::class, $documenterMock);

        // Execute the command
        $this->artisan('documenter:generate')
             ->assertSuccessful()
             ->expectsOutput('No documentation was generated.');

        // Verify that File::put was not called
        File::shouldNotHaveReceived('put');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}