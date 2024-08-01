<?php

use Elalecs\LaravelDocumenter\Generators\ModelDocumenter;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->config = [
        'model_path' => 'app/Models'
    ];
    $this->documenter = new ModelDocumenter($this->config);
});

it('generates model documentation', function () {
    // Mock File facade
    File::shouldReceive('allFiles')
        ->once()
        ->with('app/Models')
        ->andReturn([
            new SplFileInfo('app/Models/TestModel.php'),
            new SplFileInfo('app/Models/AnotherModel.php')
        ]);

    File::shouldReceive('get')
        ->andReturn('{{modelName}} {{description}} {{tableName}} {{fillable}} {{relationships}} {{scopes}} {{attributes}}');

    // Mock ReflectionClass
    $mockReflection = Mockery::mock('overload:ReflectionClass');
    $mockReflection->shouldReceive('getShortName')->andReturn('TestModel', 'AnotherModel');
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description This is a test model */');
    $mockReflection->shouldReceive('getMethods')->andReturn([]);

    // Mock model instances
    $mockModel = Mockery::mock('overload:App\Models\TestModel');
    $mockModel->shouldReceive('getTable')->andReturn('test_table');
    $mockModel->shouldReceive('getFillable')->andReturn(['name', 'email']);

    ob_start();
    $result = $this->documenter->generate();
    $output = ob_get_clean();

    expect($result)->toContain('TestModel')
        ->toContain('AnotherModel')
        ->toContain('This is a test model')
        ->toContain('test_table')
        ->toContain('name, email');

    expect($output)->toContain('Model documentation generated.');
});

it('gets models from the specified path', function () {
    File::shouldReceive('allFiles')
        ->once()
        ->with('app/Models')
        ->andReturn([
            new SplFileInfo('app/Models/TestModel.php'),
            new SplFileInfo('app/Models/SubFolder/NestedModel.php')
        ]);

    $method = new ReflectionMethod(ModelDocumenter::class, 'getModels');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter);

    expect($result)->toBe([
        'App\\Models\\TestModel',
        'App\\Models\\SubFolder\\NestedModel'
    ]);
});

it('documents a model', function () {
    File::shouldReceive('get')
        ->once()
        ->andReturn('{{modelName}} {{description}} {{tableName}} {{fillable}} {{relationships}} {{scopes}} {{attributes}}');

    $mockReflection = Mockery::mock('overload:ReflectionClass');
    $mockReflection->shouldReceive('getShortName')->andReturn('TestModel');
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description This is a test model */');
    $mockReflection->shouldReceive('getMethods')->andReturn([]);

    $mockModel = Mockery::mock('overload:App\Models\TestModel');
    $mockModel->shouldReceive('getTable')->andReturn('test_table');
    $mockModel->shouldReceive('getFillable')->andReturn(['name', 'email']);

    $method = new ReflectionMethod(ModelDocumenter::class, 'documentModel');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, 'App\Models\TestModel');

    expect($result)->toContain('TestModel')
        ->toContain('This is a test model')
        ->toContain('test_table')
        ->toContain('name, email');
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

    $mockDocumenter = Mockery::mock(ModelDocumenter::class)->makePartial();
    $mockDocumenter->shouldReceive('isRelationshipMethod')->andReturn(true);

    $method = new ReflectionMethod(ModelDocumenter::class, 'getRelationships');
    $method->setAccessible(true);

    $result = $method->invoke($mockDocumenter, $mockReflection);

    expect($result)->toBe('testRelation');
});

it('checks if method is a relationship', function () {
    $mockMethod = Mockery::mock(ReflectionMethod::class);
    $mockMethod->shouldReceive('getFileName')->andReturn(__FILE__);
    $mockMethod->shouldReceive('getStartLine')->andReturn(__LINE__);
    $mockMethod->shouldReceive('getEndLine')->andReturn(__LINE__ + 3);

    $mockDocumenter = Mockery::mock(ModelDocumenter::class)->makePartial();
    $mockDocumenter->shouldReceive('getMethodBody')->andReturn('return $this->hasMany(OtherModel::class);');

    $method = new ReflectionMethod(ModelDocumenter::class, 'isRelationshipMethod');
    $method->setAccessible(true);

    $result = $method->invoke($mockDocumenter, $mockMethod);

    expect($result)->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});