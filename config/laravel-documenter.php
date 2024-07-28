<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | This value determines the path where the generated documentation
    | will be saved. By default, it uses the storage path of your Laravel
    | application.
    |
    */
    'output_path' => storage_path('app/documentation'),

    /*
    |--------------------------------------------------------------------------
    | Documented Components
    |--------------------------------------------------------------------------
    |
    | This array lists the components that should be documented. You can
    | comment out or remove any components you don't want to include in
    | the documentation.
    |
    */
    'components' => [
        'models' => true,
        'controllers' => true,
        'filament_resources' => true,
        'jobs' => true,
        'events' => true,
        'middlewares' => true,
        'rules' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | These values determine the namespaces where the documenter should look
    | for the different components. Adjust these if your project structure
    | differs from the Laravel default.
    |
    */
    'namespaces' => [
        'models' => 'App\\Models',
        'controllers' => 'App\\Http\\Controllers',
        'filament_resources' => 'App\\Filament\\Resources',
        'jobs' => 'App\\Jobs',
        'events' => 'App\\Events',
        'middlewares' => 'App\\Http\\Middleware',
        'rules' => 'App\\Rules',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    |
    | You can exclude specific files or patterns from documentation here.
    | This is useful for ignoring auto-generated files or specific components
    | you don't want to include in the documentation.
    |
    */
    'exclude' => [
        // 'App\\Models\\TemporaryModel',
        // 'App\\Http\\Controllers\\*Controller',
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Sections
    |--------------------------------------------------------------------------
    |
    | Customize which sections should be included in the documentation for
    | each component type. You can add or remove sections as needed.
    |
    */
    'sections' => [
        'models' => [
            'properties' => true,
            'relationships' => true,
            'methods' => true,
            'events' => true,
        ],
        'controllers' => [
            'methods' => true,
            'middleware' => true,
        ],
        'filament_resources' => [
            'fields' => true,
            'forms' => true,
            'tables' => true,
            'actions' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Documenters
    |--------------------------------------------------------------------------
    |
    | If you need to document custom components or override the default
    | documentation behavior, you can register your custom documenter classes here.
    |
    */
    'custom_documenters' => [
        // 'custom_component' => \Your\Custom\Documenter::class,
    ],
];