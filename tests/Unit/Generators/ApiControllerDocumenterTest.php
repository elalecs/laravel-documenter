<?php

use Elalecs\LaravelDocumenter\Generators\ApiControllerDocumenter;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->config = [
        // Add any necessary configuration here
    ];
    $this->documenter = new ApiControllerDocumenter($this->config);
});

it('generates documentation for API controllers', function () {
    File::shouldReceive('allFiles')
        ->once()
        ->andReturn([
            new SplFileInfo('TestController.php'),
            new SplFileInfo('AnotherController.php')
        ]);

    File::shouldReceive('get')
        ->andReturn('{{controllerName}} {{endpoints}}');

    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getShortName')->andReturn('TestController');
    $mockReflection->shouldReceive('getMethods')->andReturn([]);

    $reflectionProperty = new ReflectionProperty(ApiControllerDocumenter::class, 'reflection');
    $reflectionProperty->setAccessible(true);
    $reflectionProperty->setValue($this->documenter, $mockReflection);

    $result = $this->documenter->generate();

    expect($result)->toContain('TestController');
    expect($result)->toContain('AnotherController');
});

it('documents a controller', function () {
    File::shouldReceive('get')
        ->once()
        ->andReturn('{{controllerName}} {{endpoints}}');

    $method = new ReflectionMethod(ApiControllerDocumenter::class, 'documentController');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, 'App\Http\Controllers\Api\TestController');

    expect($result)->toContain('TestController');
});

it('identifies endpoint methods', function () {
    $mockMethod = Mockery::mock(ReflectionMethod::class);
    $mockMethod->shouldReceive('isConstructor')->andReturn(false);
    $mockMethod->shouldReceive('getName')->andReturn('index');
    $mockMethod->shouldReceive('getDocComment')->andReturn('/** @method GET */');

    $method = new ReflectionMethod(ApiControllerDocumenter::class, 'isEndpointMethod');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockMethod);

    expect($result)->toBeTrue();
});

it('documents an endpoint', function () {
    $mockMethod = Mockery::mock(ReflectionMethod::class);
    $mockMethod->shouldReceive('getName')->andReturn('index');
    $mockMethod->shouldReceive('getDocComment')->andReturn('/** 
        * @method GET
        * @route /api/test
        * @description Test endpoint
        */');

    $method = new ReflectionMethod(ApiControllerDocumenter::class, 'documentEndpoint');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockMethod);

    expect($result)->toContain('GET');
    expect($result)->toContain('/api/test');
    expect($result)->toContain('index');
    expect($result)->toContain('Test endpoint');
});