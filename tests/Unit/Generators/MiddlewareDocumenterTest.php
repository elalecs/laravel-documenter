<?php

use Elalecs\LaravelDocumenter\Generators\MiddlewareDocumenter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;
use Illuminate\Contracts\Http\Kernel;
use ReflectionClass;
use ReflectionMethod;

beforeEach(function () {
    $this->config = [
        'middleware_path' => 'app/Http/Middleware'
    ];
    $this->documenter = new MiddlewareDocumenter($this->config);
});

it('generates middleware documentation', function () {
    File::shouldReceive('allFiles')
        ->once()
        ->andReturn([
            new SplFileInfo('TestMiddleware.php'),
            new SplFileInfo('AnotherMiddleware.php')
        ]);

    File::shouldReceive('get')
        ->andReturn('{{middlewareName}} {{description}} {{handleMethod}} {{registration}}');

    $result = $this->documenter->generate();

    expect($result)->toContain('TestMiddleware')
        ->toContain('AnotherMiddleware');
});

it('documents a middleware', function () {
    File::shouldReceive('get')
        ->once()
        ->andReturn('{{middlewareName}} {{description}} {{handleMethod}} {{registration}}');

    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getShortName')->andReturn('TestMiddleware');
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description This is a test middleware */');

    $mockHandleMethod = Mockery::mock(ReflectionMethod::class);
    $mockHandleMethod->shouldReceive('getDocComment')->andReturn('/** @description This method handles the middleware */');
    $mockHandleMethod->shouldReceive('getParameters')->andReturn([]);
    $mockReflection->shouldReceive('getMethod')->with('handle')->andReturn($mockHandleMethod);

    $mockKernel = Mockery::mock(Kernel::class);
    $mockKernel->shouldReceive('getMiddlewareGroups')->andReturn(['web' => ['App\Http\Middleware\TestMiddleware']]);
    $mockKernel->shouldReceive('getRouteMiddleware')->andReturn(['test' => 'App\Http\Middleware\TestMiddleware']);
    App::shouldReceive('make')->with(Kernel::class)->andReturn($mockKernel);

    $method = new ReflectionMethod(MiddlewareDocumenter::class, 'documentMiddleware');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, 'App\Http\Middleware\TestMiddleware');

    expect($result)->toContain('TestMiddleware')
        ->toContain('This is a test middleware')
        ->toContain('This method handles the middleware')
        ->toContain('Registered in the \'web\' middleware group')
        ->toContain('Registered as route middleware with key \'test\'');
});

it('gets middleware description', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description This is a test middleware */');

    $method = new ReflectionMethod(MiddlewareDocumenter::class, 'getMiddlewareDescription');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toBe('This is a test middleware');
});

it('gets handle method', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockHandleMethod = Mockery::mock(ReflectionMethod::class);
    $mockHandleMethod->shouldReceive('getDocComment')->andReturn('/** @description This method handles the middleware */');
    $mockHandleMethod->shouldReceive('getParameters')->andReturn([]);
    $mockReflection->shouldReceive('getMethod')->with('handle')->andReturn($mockHandleMethod);

    $method = new ReflectionMethod(MiddlewareDocumenter::class, 'getHandleMethod');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toContain('This method handles the middleware')
        ->toContain('No parameters.');
});

it('gets method parameters', function () {
    $mockMethod = Mockery::mock(ReflectionMethod::class);
    $mockParameter = Mockery::mock(ReflectionParameter::class);
    $mockParameter->shouldReceive('getName')->andReturn('request');
    $mockParameter->shouldReceive('hasType')->andReturn(true);
    $mockType = Mockery::mock(ReflectionType::class);
    $mockType->shouldReceive('getName')->andReturn('Illuminate\Http\Request');
    $mockParameter->shouldReceive('getType')->andReturn($mockType);
    $mockMethod->shouldReceive('getParameters')->andReturn([$mockParameter]);
    $mockMethod->shouldReceive('getDocComment')->andReturn('/** @param Illuminate\Http\Request $request The incoming request */');

    $method = new ReflectionMethod(MiddlewareDocumenter::class, 'getMethodParameters');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockMethod);

    expect($result)->toContain('`request` (Illuminate\Http\Request): The incoming request');
});

it('gets middleware registration', function () {
    $mockKernel = Mockery::mock(Kernel::class);
    $mockKernel->shouldReceive('getMiddlewareGroups')->andReturn(['web' => ['App\Http\Middleware\TestMiddleware']]);
    $mockKernel->shouldReceive('getRouteMiddleware')->andReturn(['test' => 'App\Http\Middleware\TestMiddleware']);
    App::shouldReceive('make')->with(Kernel::class)->andReturn($mockKernel);

    $method = new ReflectionMethod(MiddlewareDocumenter::class, 'getMiddlewareRegistration');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, 'App\Http\Middleware\TestMiddleware');

    expect($result)->toContain('Registered in the \'web\' middleware group')
        ->toContain('Registered as route middleware with key \'test\'');
});