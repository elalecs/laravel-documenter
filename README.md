# Laravel Documenter

Laravel Documenter is an automated documentation generator for Laravel and Filament projects. Its primary purpose is to generate or extend the CONTRIBUTING.md file of your project, providing a comprehensive overview of your project's structure and components.

## Features

- Automatically generates or extends the CONTRIBUTING.md file in your Laravel project
- Documents key components: Models, Filament Resources, API Controllers, Jobs, Events, Middlewares, and Rules
- Uses customizable stubs for flexible documentation formatting
- Helps quickly onboard new developers by providing an up-to-date project overview
- Integrates seamlessly with Laravel and Filament projects

## Requirements

- PHP 7.3 or higher
- Laravel 8.0 or higher

## Installation

You can install the package via composer:

```bash
composer require elalecs/laravel-documenter --dev
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Elalecs\LaravelDocumenter\LaravelDocumenterServiceProvider" --tag="config"
```

This will create a `config/laravel-documenter.php` file where you can customize the behavior of the package.

## Usage

To generate or update your project's CONTRIBUTING.md file:

```bash
php artisan documenter:generate
```

This command will:
1. Analyze your project structure
2. Generate documentation for each component (Models, Filament Resources, etc.)
3. Create or update the CONTRIBUTING.md file in your project root

You can also generate documentation for specific components:

```bash
php artisan documenter:generate --type model
```

Available types are: `model`, `filament`, `api`, and `general`.

## Customization

You can customize the documentation output by publishing and modifying the stub files:

```bash
php artisan vendor:publish --provider="Elalecs\LaravelDocumenter\LaravelDocumenterServiceProvider" --tag="stubs"
```

This will copy the stub files to your `resources/views/vendor/laravel-documenter` directory. After publishing, update your `config/laravel-documenter.php` file to point to your custom stubs:

```php
'stubs_path' => resource_path('views/vendor/laravel-documenter'),
```

## Important: DocBlocks

For Laravel Documenter to function effectively, it's crucial that your code includes properly formatted DocBlocks. These DocBlocks should be present on:

- Classes
- Methods (especially `handle()` methods in Jobs and Middleware, and `passes()` methods in Rules)
- Properties

Include `@description` tags in your DocBlocks to provide detailed information about the purpose and functionality of your components.

Example:

```php
/**
 * @description This job processes user uploads and generates thumbnails.
 */
class ProcessUserUpload implements ShouldQueue
{
    // ...
}
```

The more comprehensive your DocBlocks, the more detailed and useful the generated documentation will be.

## Updating

When updating the package, make sure to republish the configuration file and clear the config cache:

```bash
composer update elalecs/laravel-documenter
php artisan vendor:publish --provider="Elalecs\LaravelDocumenter\LaravelDocumenterServiceProvider" --tag="config" --force
php artisan config:clear
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate.

For more details on how to contribute, please check our [CONTRIBUTING.md](CONTRIBUTING.md) file.

## License

The Laravel Documenter is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Credits

- [Alex Galindo](https://github.com/elalecs)