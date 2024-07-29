<?php

use Elalecs\LaravelDocumenter\Commands\GenerateDocumentation;
use Elalecs\LaravelDocumenter\LaravelDocumenter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->documenter = Mockery::mock(LaravelDocumenter::class);
    $this->command = new GenerateDocumentation($this->documenter);
    $this->commandTester = new CommandTester($this->command);
});

afterEach(function () {
    Mockery::close();
});

it('generates documentation without options', function () {
    $components = ['model', 'filamentResource', 'apiController', 'job', 'event', 'middleware', 'rule'];
    foreach ($components as $component) {
        $this->documenter->shouldReceive('generate' . ucfirst($component) . 'Documentation')
            ->once()
            ->andReturn("$component documentation");
    }

    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('get')->andReturn('{{generatedDocumentation}}');
    File::shouldReceive('put')->once();

    $this->commandTester->execute([]);

    expect($this->commandTester->getDisplay())->toContain('Documentation generated successfully!');
});

it('generates documentation for a specific component', function () {
    $this->documenter->shouldReceive('generateModelDocumentation')
        ->once()
        ->andReturn('model documentation');

    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('get')->andReturn('{{generatedDocumentation}}');
    File::shouldReceive('put')->once();

    $this->commandTester->execute(['--component' => 'model']);

    expect($this->commandTester->getDisplay())->toContain('Model documentation generated.');
});

it('generates documentation with custom output path', function () {
    $customOutput = 'custom/path';
    $components = ['model', 'filamentResource', 'apiController', 'job', 'event', 'middleware', 'rule'];
    foreach ($components as $component) {
        $this->documenter->shouldReceive('generate' . ucfirst($component) . 'Documentation')
            ->once()
            ->andReturn("$component documentation");
    }

    Config::shouldReceive('set')
        ->with('laravel-documenter.output_path', $customOutput)
        ->once();

    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('get')->andReturn('{{generatedDocumentation}}');
    File::shouldReceive('put')->once();

    $this->commandTester->execute(['--output' => $customOutput]);

    expect($this->commandTester->getDisplay())->toContain('Documentation generated successfully!');
});

it('handles unknown component', function () {
    $this->commandTester->execute(['--component' => 'unknown']);

    expect($this->commandTester->getDisplay())->toContain('Unknown component: unknown');
});

it('handles missing contributing stub file', function () {
    $components = ['model', 'filamentResource', 'apiController', 'job', 'event', 'middleware', 'rule'];
    foreach ($components as $component) {
        $this->documenter->shouldReceive('generate' . ucfirst($component) . 'Documentation')
            ->andReturn('');
    }

    File::shouldReceive('exists')->andReturn(false);

    $this->commandTester->execute([]);

    expect($this->commandTester->getDisplay())->toContain('Contributing stub file not found');
});

it('generates contributing file successfully', function () {
    $components = ['model', 'filamentResource', 'apiController', 'job', 'event', 'middleware', 'rule'];
    foreach ($components as $component) {
        $this->documenter->shouldReceive('generate' . ucfirst($component) . 'Documentation')
            ->andReturn('');
    }

    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('get')->andReturn('{{generatedDocumentation}}');
    File::shouldReceive('put')->once();

    $this->commandTester->execute([]);

    expect($this->commandTester->getDisplay())->toContain('CONTRIBUTING.md file generated');
});