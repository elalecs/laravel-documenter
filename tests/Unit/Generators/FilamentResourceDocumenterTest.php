<?php

use Elalecs\LaravelDocumenter\Generators\FilamentResourceDocumenter;
use Illuminate\Support\Facades\File;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\EditAction;

beforeEach(function () {
    $this->config = [
        'filament_resource_path' => 'app/Filament/Resources'
    ];
    $this->documenter = new FilamentResourceDocumenter($this->config);
});

it('generates documentation for Filament resources', function () {
    File::shouldReceive('allFiles')
        ->once()
        ->andReturn([
            new SplFileInfo('TestResource.php'),
            new SplFileInfo('AnotherResource.php')
        ]);

    File::shouldReceive('get')
        ->andReturn('{{resourceName}} {{modelName}} {{formFields}} {{tableColumns}} {{filters}} {{actions}}');

    $result = $this->documenter->generate();

    expect($result)->toContain('TestResource')
        ->toContain('AnotherResource');
});

it('documents a Filament resource', function () {
    File::shouldReceive('get')
        ->once()
        ->andReturn('{{resourceName}} {{modelName}} {{formFields}} {{tableColumns}} {{filters}} {{actions}}');

    $mockReflection = Mockery::mock(ReflectionClass::class);
    $mockReflection->shouldReceive('getShortName')->andReturn('TestResource');
    
    $mockReflection->shouldReceive('getMethod')->andReturnUsing(function($methodName) {
        $mockMethod = Mockery::mock(ReflectionMethod::class);
        switch ($methodName) {
            case 'getModelLabel':
                $mockMethod->shouldReceive('invoke')->andReturn('Test Model');
                break;
            case 'form':
                $mockMethod->shouldReceive('invoke')->andReturn($this->getMockForm());
                break;
            case 'table':
                $mockMethod->shouldReceive('invoke')->andReturn($this->getMockTable());
                break;
            case 'getFilters':
                $mockMethod->shouldReceive('invoke')->andReturn($this->getMockFilters());
                break;
            case 'getActions':
                $mockMethod->shouldReceive('invoke')->andReturn($this->getMockActions());
                break;
        }
        return $mockMethod;
    });

    $mockReflection->shouldReceive('hasMethod')->andReturn(true);

    $method = new ReflectionMethod(FilamentResourceDocumenter::class, 'documentResource');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, 'App\Filament\Resources\TestResource');

    expect($result)->toContain('TestResource')
        ->toContain('Test Model')
        ->toContain('Form Fields')
        ->toContain('Table Columns')
        ->toContain('Filters')
        ->toContain('Actions');
});

it('gets component description', function () {
    $textInput = TextInput::make('name')->label('Name')->placeholder('Enter name');
    $method = new ReflectionMethod(FilamentResourceDocumenter::class, 'getComponentDescription');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $textInput);

    expect($result)->toContain('Type: TextInput')
        ->toContain("Label: 'Name'")
        ->toContain("Placeholder: 'Enter name'");
});

it('formats schema components', function () {
    $components = [
        TextInput::make('name')->label('Name'),
        Select::make('type')->label('Type')->options(['option1' => 'Option 1', 'option2' => 'Option 2'])
    ];

    $method = new ReflectionMethod(FilamentResourceDocumenter::class, 'formatSchemaComponents');
    $method->setAccessible(true);

    $result = $method->invoke($this->documenter, $components, 'Test Components');

    expect($result)->toContain('### Test Components')
        ->toContain('**name**:')
        ->toContain('**type**:');
});

function getMockForm()
{
    $mockForm = Mockery::mock(Filament\Forms\Form::class);
    $mockForm->shouldReceive('getSchema')->andReturn([
        TextInput::make('name')->label('Name')->placeholder('Enter name'),
        Select::make('type')->label('Type')->options(['option1' => 'Option 1', 'option2' => 'Option 2'])
    ]);
    return $mockForm;
}

function getMockTable()
{
    $mockTable = Mockery::mock(Filament\Tables\Table::class);
    $mockTable->shouldReceive('getColumns')->andReturn([
        TextColumn::make('name')->label('Name'),
        TextColumn::make('type')->label('Type')
    ]);
    return $mockTable;
}

function getMockFilters()
{
    return [
        SelectFilter::make('type')->label('Type')->options(['option1' => 'Option 1', 'option2' => 'Option 2'])
    ];
}

function getMockActions()
{
    return [
        EditAction::make('edit')->label('Edit')
    ];
}