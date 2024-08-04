<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Output Path
    |--------------------------------------------------------------------------
    |
    | This value determines the path where the generated documentation
    | will be saved. By default, it will be saved in the root of your
    | project as CONTRIBUTING.md.
    |
    */
    'output_path' => [
        'main' => base_path('CONTRIBUTING.md'),
        'general' => base_path('CONTRIBUTING.GENERAL.md'),
        'model' => base_path('CONTRIBUTING.MODEL.md'),
        'api' => base_path('CONTRIBUTING.API.md'),
        'filament' => base_path('CONTRIBUTING.FILAMENT.md'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stubs Path
    |--------------------------------------------------------------------------
    |
    | This value determines the path where the package will look for the stub
    | files used to generate the documentation. After publishing the assets,
    | this path will point to the published stubs in your project.
    |
    */
    'stubs_path' => __DIR__.'/../vendor/elalecs/laravel-documenter/src/Stubs',

    /*
    |--------------------------------------------------------------------------
    | Documenters Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can specify the configuration for each documenter.
    |
    */
    'documenters' => [
        'general' => [
            'path' => app_path(),
            'exclude' => ['Models', 'Filament'],
        ],
        'model' => [
            'path' => app_path('Models'),
        ],
        'api' => [
            'path' => base_path('routes'),
            'files' => ['api.php'], // Only document routes in api.php
        ],
        'filament' => [
            'path' => app_path('Filament'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP-Parser Options
    |--------------------------------------------------------------------------
    |
    | Here you can specify the options for PHP-Parser.
    |
    */
    'php_parser_options' => [
        'kind' => PhpParser\PhpVersion::fromString('8.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | This value determines if logging is enabled and the level of logging
    | that should be used. You can set the logging level to 'debug', 'info',
    | 'warning', or 'error'.
    |
    */
    'logging' => [
        'enabled' => false,
        'level' => 'warning', // 'debug', 'info', 'warning', 'error'
    ],
];