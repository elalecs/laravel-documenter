<?php

use Elalecs\LaravelDocumenter\Generators\ModelDocumenter;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

beforeEach(function () {
    $this->config = [
        'model_path' => 'app/Models'
    ];
    $this->documenter = new ModelDocumenter($this->config);
});

it('generates model documentation', function () {
    File::shouldReceive('allFiles')
        ->once()
        ->andReturn([
            new SplFileInfo('TestModel.php'),
            new SplFileInfo('AnotherModel.php')
        ]);

    File::shouldReceive('get')
        ->andReturn('{{modelName}} {{description}} {{tableName}} {{fillable}} {{relationships}} {{scopes}} {{attributes}}');

    $result = $this->documenter->generate();

    expect($result)->toContain('TestModel')
        ->toContain('AnotherModel');
});

it('documents a model', function () {
    File::shouldReceive('get')
        ->once()
        ->andReturn('{{modelName}} {{description}} {{tableName}} {{fillable}} {{relationships}} {{scopes}} {{attributes}}');

    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getShortName')->andReturn('TestModel');
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description This is a test model */');

    $mockModel = Mockery::mock(stdClass::class);
    $mockModel->shouldReceive('getTable')->andReturn('test_table');
    $mockModel->shouldReceive('getFillable')->andReturn(['name', 'email']);

    $mockMethod = Mockery::mock(ReflectionMethod::class);
    $mockMethod->shouldReceive('getName')->andReturn('testRelation');
    $mockReflection->shouldReceive('getMethods')->andReturn([$mockMethod]);

    $method = new ReflectionMethod(ModelDocumenter::class, 'documentModel');
    $method->setAccessible(true);

    $isRelationshipMethod = Mockery::mock(ReflectionMethod::class);
    $isRelationshipMethod->shouldReceive('invoke')->andReturn(true);
    $this->documenter->isRelationshipMethod = $isRelationshipMethod;

    $result = $method->invoke($this->documenter, 'App\Models\TestModel');

    expect($result)->toContain('TestModel')
        ->toContain('This is a test model')
        ->toContain('test_table')
        ->toContain('name, email')
        ->toContain('testRelation');
});

it('gets model description', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description This is a test model */');

    $method = new ReflectionMethod(ModelDocumenter::class, 'getModelDescription');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toBe('This is a test model');
});

it('gets table name', function () {
    $mockModel = Mockery::mock(stdClass::class);
    $mockModel->shouldReceive('getTable')->andReturn('test_table');

    $method = new ReflectionMethod(ModelDocumenter::class, 'getTableName');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, get_class($mockModel));

    expect($result)->toBe('test_table');
});

it('gets fillable attributes', function () {
    $mockModel = Mockery::mock(stdClass::class);
    $mockModel->shouldReceive('getFillable')->andReturn(['name', 'email']);

    $method = new ReflectionMethod(ModelDocumenter::class, 'getFillable');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, get_class($mockModel));

    expect($result)->toBe('name, email');
});

it('gets relationships', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockMethod = Mockery::mock(ReflectionMethod::class);
    $mockMethod->shouldReceive('getName')->andReturn('testRelation');
    $mockReflection->shouldReceive('getMethods')->andReturn([$mockMethod]);

    $isRelationshipMethod = Mockery::mock(ReflectionMethod::class);
    $isRelationshipMethod->shouldReceive('invoke')->andReturn(true);
    $this->documenter->isRelationshipMethod = $isRelationshipMethod;

    $method = new ReflectionMethod(ModelDocumenter::class, 'getRelationships');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toBe('testRelation');
});

it('checks if method is a relationship', function () {
    $mockMethod = Mockery::mock(ReflectionMethod::class);
    $mockMethod->shouldReceive('getFileName')->andReturn(__FILE__);
    $mockMethod->shouldReceive('getStartLine')->andReturn(__LINE__);
    $mockMethod->shouldReceive('getEndLine')->andReturn(__LINE__ + 3);

    $method = new ReflectionMethod(ModelDocumenter::class, 'isRelationshipMethod');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockMethod);

    expect($result)->toBeBoolean();
});