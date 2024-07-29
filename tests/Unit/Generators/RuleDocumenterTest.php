<?php

use Elalecs\LaravelDocumenter\Generators\RuleDocumenter;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->config = [
        'rule_path' => 'app/Rules'
    ];
    $this->documenter = new RuleDocumenter($this->config);
});

it('generates documentation for rules', function () {
    File::shouldReceive('allFiles')
        ->once()
        ->andReturn([
            new SplFileInfo('TestRule.php'),
            new SplFileInfo('AnotherRule.php')
        ]);

    File::shouldReceive('get')
        ->andReturn('{{ruleName}} {{description}} {{passesMethod}} {{message}} {{constructorParameters}}');

    $result = $this->documenter->generate();

    expect($result)->toContain('TestRule')
        ->toContain('AnotherRule');
});

it('documents a rule', function () {
    File::shouldReceive('get')
        ->once()
        ->andReturn('{{ruleName}} {{description}} {{passesMethod}} {{message}} {{constructorParameters}}');

    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getShortName')->andReturn('TestRule');
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description Test rule description */');

    $mockPassesMethod = Mockery::mock(ReflectionMethod::class);
    $mockPassesMethod->shouldReceive('getDocComment')->andReturn('/** @description Passes method description */');
    $mockPassesMethod->shouldReceive('getParameters')->andReturn([]);
    $mockReflection->shouldReceive('getMethod')->with('passes')->andReturn($mockPassesMethod);

    $mockMessageMethod = Mockery::mock(ReflectionMethod::class);
    $mockMessageMethod->shouldReceive('getDocComment')->andReturn('/** @return string The error message */');
    $mockReflection->shouldReceive('hasMethod')->with('message')->andReturn(true);
    $mockReflection->shouldReceive('getMethod')->with('message')->andReturn($mockMessageMethod);

    $mockConstructor = Mockery::mock(ReflectionMethod::class);
    $mockConstructor->shouldReceive('getParameters')->andReturn([]);
    $mockReflection->shouldReceive('getConstructor')->andReturn($mockConstructor);

    $method = new ReflectionMethod(RuleDocumenter::class, 'documentRule');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, 'App\Rules\TestRule');

    expect($result)->toContain('TestRule')
        ->toContain('Test rule description')
        ->toContain('Passes method description')
        ->toContain('The error message');
});

it('gets rule description', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description Test rule description */');

    $method = new ReflectionMethod(RuleDocumenter::class, 'getRuleDescription');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toBe('Test rule description');
});

it('gets passes method', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockPassesMethod = Mockery::mock(ReflectionMethod::class);
    $mockPassesMethod->shouldReceive('getDocComment')->andReturn('/** @description Passes method description */');
    $mockPassesMethod->shouldReceive('getParameters')->andReturn([]);
    $mockReflection->shouldReceive('getMethod')->with('passes')->andReturn($mockPassesMethod);

    $method = new ReflectionMethod(RuleDocumenter::class, 'getPassesMethod');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toContain('Passes method description')
        ->toContain('No parameters.');
});

it('gets message', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockMessageMethod = Mockery::mock(ReflectionMethod::class);
    $mockMessageMethod->shouldReceive('getDocComment')->andReturn('/** @return string The error message */');
    $mockReflection->shouldReceive('hasMethod')->with('message')->andReturn(true);
    $mockReflection->shouldReceive('getMethod')->with('message')->andReturn($mockMessageMethod);

    $method = new ReflectionMethod(RuleDocumenter::class, 'getMessage');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toBe('string The error message');
});

it('gets constructor parameters', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockConstructor = Mockery::mock(ReflectionMethod::class);
    $mockParam = Mockery::mock(ReflectionParameter::class);
    $mockParam->shouldReceive('getName')->andReturn('testParam');
    $mockParam->shouldReceive('hasType')->andReturn(true);
    $mockType = Mockery::mock(ReflectionType::class);
    $mockType->shouldReceive('getName')->andReturn('string');
    $mockParam->shouldReceive('getType')->andReturn($mockType);
    $mockConstructor->shouldReceive('getParameters')->andReturn([$mockParam]);
    $mockConstructor->shouldReceive('getDocComment')->andReturn('/** @param string $testParam Test parameter */');
    $mockReflection->shouldReceive('getConstructor')->andReturn($mockConstructor);

    $method = new ReflectionMethod(RuleDocumenter::class, 'getConstructorParameters');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toContain('`testParam` (string): Test parameter');
});