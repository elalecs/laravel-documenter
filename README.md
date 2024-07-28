# Laravel Documenter

Laravel Documenter is an automated documentation generator for Laravel and Filament projects. Its primary purpose is to generate or extend the CONTRIBUTING.md file of your project, providing a comprehensive overview of your project's structure and components.

## Features

- Automatically generates or extends the CONTRIBUTING.md file in your Laravel project
- Documents key components: Models, Filament Resources, API Controllers, Jobs, Events, Middlewares, and Rules
- Uses customizable stubs for flexible documentation formatting
- Helps quickly onboard new developers by providing an up-to-date project overview
- Integrates seamlessly with Laravel and Filament projects

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

## Customization

You can customize the documentation output by modifying the stub files located in the `vendor/elalecs/laravel-documenter/src/Stubs` directory. After publishing the assets, you can find and edit these stubs in your project's `resources/views/vendor/laravel-documenter` directory.

To publish the stubs:

```bash
php artisan vendor:publish --provider="Elalecs\LaravelDocumenter\LaravelDocumenterServiceProvider" --tag="stubs"
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The Laravel Documenter is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Support

If you discover any security-related issues, please email your.email@example.com instead of using the issue tracker.

## Credits

- [Alex Galindo](https://github.com/elalecs)