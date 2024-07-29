<?php

use Elalecs\LaravelDocumenter\Generators\EventDocumenter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\App;

beforeEach(function () {
    $this->config = [
        'event_path' => 'app/Events'
    ];
    $this->documenter = new EventDocumenter($this->config);
});

it('generates event documentation', function () {
    File::shouldReceive('allFiles')
        ->once()
        ->andReturn([
            new SplFileInfo('TestEvent.php'),
            new SplFileInfo('AnotherEvent.php')
        ]);

    File::shouldReceive('get')
        ->andReturn('{{eventName}} {{description}} {{properties}} {{listeners}}');

    $result = $this->documenter->generate();

    expect($result)->toContain('TestEvent');
    expect($result)->toContain('AnotherEvent');
});

it('documents an event', function () {
    File::shouldReceive('get')
        ->once()
        ->andReturn('{{eventName}} {{description}} {{properties}} {{listeners}}');

    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getShortName')->andReturn('TestEvent');
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description Test event description */');

    $mockProperty = Mockery::mock(ReflectionProperty::class);
    $mockProperty->shouldReceive('getName')->andReturn('testProperty');
    $mockProperty->shouldReceive('getDocComment')->andReturn('/** @var string @description Test property description */');
    $mockReflection->shouldReceive('getProperties')->andReturn([$mockProperty]);

    $mockEventServiceProvider = Mockery::mock(\App\Providers\EventServiceProvider::class);
    $mockEventServiceProvider->shouldReceive('listens')->andReturn([
        'App\Events\TestEvent' => ['App\Listeners\TestListener']
    ]);
    App::shouldReceive('getProvider')->andReturn($mockEventServiceProvider);

    $result = $this->documenter->documentEvent('App\Events\TestEvent');

    expect($result)->toContain('TestEvent')
        ->toContain('Test event description')
        ->toContain('testProperty')
        ->toContain('Test property description')
        ->toContain('App\Listeners\TestListener');
});

it('gets event description', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getDocComment')->andReturn('/** @description Test event description */');

    $result = $this->documenter->getEventDescription($mockReflection);

    expect($result)->toBe('Test event description');
});

it('gets event properties', function () {
    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockProperty = Mockery::mock(ReflectionProperty::class);
    $mockProperty->shouldReceive('getName')->andReturn('testProperty');
    $mockProperty->shouldReceive('getDocComment')->andReturn('/** @var string @description Test property description */');
    $mockReflection->shouldReceive('getProperties')->andReturn([$mockProperty]);

    $result = $this->documenter->getEventProperties($mockReflection);

    expect($result)->toContain('testProperty')
        ->toContain('string')
        ->toContain('Test property description');
});

it('gets event listeners', function () {
    $mockEventServiceProvider = Mockery::mock(\App\Providers\EventServiceProvider::class);
    $mockEventServiceProvider->shouldReceive('listens')->andReturn([
        'App\Events\TestEvent' => ['App\Listeners\TestListener']
    ]);
    App::shouldReceive('getProvider')->andReturn($mockEventServiceProvider);

    $result = $this->documenter->getEventListeners('App\Events\TestEvent');

    expect($result)->toContain('App\Listeners\TestListener');
});