<?php

use Elalecs\LaravelDocumenter\Generators\JobDocumenter;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Queue\ShouldQueue;

beforeEach(function () {
    $this->config = [
        'job_path' => 'app/Jobs'
    ];
    $this->documenter = new JobDocumenter($this->config);
});

it('generates job documentation', function () {
    File::shouldReceive('allFiles')
        ->once()
        ->andReturn([
            new SplFileInfo('TestJob.php'),
            new SplFileInfo('AnotherJob.php')
        ]);

    File::shouldReceive('get')
        ->andReturn('{{jobName}} {{queue}} {{description}} {{parameters}} {{handleMethod}} {{implementedInterfaces}}');

    $result = $this->documenter->generate();

    expect($result)->toContain('TestJob')
        ->toContain('AnotherJob');
});

it('documents a job', function () {
    File::shouldReceive('get')
        ->once()
        ->andReturn('{{jobName}} {{queue}} {{description}} {{parameters}} {{handleMethod}} {{implementedInterfaces}}');

    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getShortName')->andReturn('TestJob');
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description This is a test job */');
    
    $mockReflection->shouldReceive('hasProperty')->with('queue')->andReturn(true);
    $mockQueueProperty = Mockery::mock(ReflectionProperty::class);
    $mockQueueProperty->shouldReceive('getValue')->andReturn('test-queue');
    $mockReflection->shouldReceive('getProperty')->with('queue')->andReturn($mockQueueProperty);

    $mockConstructor = Mockery::mock(ReflectionMethod::class);
    $mockConstructor->shouldReceive('getParameters')->andReturn([]);
    $mockReflection->shouldReceive('getConstructor')->andReturn($mockConstructor);

    $mockHandleMethod = Mockery::mock(ReflectionMethod::class);
    $mockHandleMethod->shouldReceive('getDocComment')->andReturn('/** @description This method handles the job */');
    $mockHandleMethod->shouldReceive('getParameters')->andReturn([]);
    $mockReflection->shouldReceive('getMethod')->with('handle')->andReturn($mockHandleMethod);

    $mockReflection->shouldReceive('getInterfaceNames')->andReturn([ShouldQueue::class]);

    $method = new ReflectionMethod(JobDocumenter::class, 'documentJob');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, 'App\Jobs\TestJob');

    expect($result)->toContain('TestJob')
        ->toContain('test-queue')
        ->toContain('This is a test job')
        ->toContain('This method handles the job')
        ->toContain(ShouldQueue::class);
});

it('gets job queue', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('hasProperty')->with('queue')->andReturn(true);
    $mockQueueProperty = Mockery::mock(ReflectionProperty::class);
    $mockQueueProperty->shouldReceive('getValue')->andReturn('test-queue');
    $mockReflection->shouldReceive('getProperty')->with('queue')->andReturn($mockQueueProperty);

    $method = new ReflectionMethod(JobDocumenter::class, 'getJobQueue');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toBe('test-queue');
});

it('gets job description', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description This is a test job */');

    $method = new ReflectionMethod(JobDocumenter::class, 'getJobDescription');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toBe('This is a test job');
});

it('gets constructor parameters', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockConstructor = Mockery::mock(ReflectionMethod::class);
    $mockParameter = Mockery::mock(ReflectionParameter::class);
    $mockParameter->shouldReceive('getName')->andReturn('testParam');
    $mockParameter->shouldReceive('hasType')->andReturn(true);
    $mockType = Mockery::mock(ReflectionType::class);
    $mockType->shouldReceive('getName')->andReturn('string');
    $mockParameter->shouldReceive('getType')->andReturn($mockType);
    $mockConstructor->shouldReceive('getParameters')->andReturn([$mockParameter]);
    $mockConstructor->shouldReceive('getDocComment')->andReturn('/** @param string $testParam Test parameter */');
    $mockReflection->shouldReceive('getConstructor')->andReturn($mockConstructor);

    $method = new ReflectionMethod(JobDocumenter::class, 'getConstructorParameters');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toContain('`testParam` (string): Test parameter');
});

it('gets handle method', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockHandleMethod = Mockery::mock(ReflectionMethod::class);
    $mockHandleMethod->shouldReceive('getDocComment')->andReturn('/** @description This method handles the job */');
    $mockHandleMethod->shouldReceive('getParameters')->andReturn([]);
    $mockReflection->shouldReceive('getMethod')->with('handle')->andReturn($mockHandleMethod);

    $method = new ReflectionMethod(JobDocumenter::class, 'getHandleMethod');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toContain('This method handles the job')
        ->toContain('No parameters.');
});

it('gets implemented interfaces', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getInterfaceNames')->andReturn([ShouldQueue::class]);

    $method = new ReflectionMethod(JobDocumenter::class, 'getImplementedInterfaces');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $mockReflection);

    expect($result)->toContain(ShouldQueue::class);
});