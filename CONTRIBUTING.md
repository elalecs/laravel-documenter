# Contributing to Laravel Documenter

Thank you for your interest in contributing to Laravel Documenter. This document provides an overview of the project's architecture and guidelines for extension and contribution.

## Project Architecture

Laravel Documenter is structured as a Laravel package, designed to generate comprehensive documentation for Laravel and Filament projects. Here's an overview of the key components:

### 1. Service Provider

`LaravelDocumenterServiceProvider.php` is the entry point of the package. It:
- Registers the package's config file
- Binds the main `LaravelDocumenter` class to the service container
- Registers the documenters

### 2. Main Class

`LaravelDocumenter.php` orchestrates the documentation generation process. It:
- Coordinates between different documenters
- Handles high-level logic for documentation generation

### 3. Command

`GenerateDocumentation.php` is an Artisan command that:
- Provides a CLI interface for generating documentation
- Handles user input for specifying components or custom output paths

### 4. Documenters

We have four main documenters:

- `GeneralDocumenter.php`: Documents all classes within `app/`, excluding Models.
- `ModelDocumenter.php`: Specifically documents models.
- `ApiDocumenter.php`: Documents API endpoints.
- `FilamentDocumenter.php`: Documents Filament resources and pages.

### 5. Base Parser Class

`BasePhpParserDocumenter.php` provides a base class for all documenters, offering common parsing functionality.

### 6. Configuration

`config/laravel-documenter.php` allows users to customize the behavior of the package.

### 7. Stubs

The package uses Blade stubs for generating documentation. These stubs are located in the `src/Stubs` directory. After publishing the assets, you can find and edit these stubs in your project's `resources/views/vendor/laravel-documenter` directory. The main stubs are:

- `contributing.blade.php`: The main template for the CONTRIBUTING.md file.
- `general-documenter.blade.php`: Template for general class documentation.
- `model-documenter.blade.php`: Template for model documentation.
- `api-documenter.blade.php`: Template for API endpoint documentation.
- `filament-documenter.blade.php`: Template for Filament resource documentation.

To publish the stubs:

```bash
php artisan vendor:publish --provider="Elalecs\LaravelDocumenter\LaravelDocumenterServiceProvider" --tag="stubs"
```

This will copy the stubs to your project's `resources/views/vendor/laravel-documenter` directory, allowing you to customize them.

## DocBlocks

DocBlocks are crucial for the proper functioning of Laravel Documenter. When contributing to this project or using it in your own projects, please ensure that your code includes comprehensive DocBlocks. Here are some guidelines:

1. Every class should have a DocBlock with a `@description` tag explaining its purpose.
2. Methods should have DocBlocks explaining their functionality.
3. Use `@param` tags to describe method parameters.
4. Use `@return` tags to describe method return values.
5. For properties, use DocBlocks to explain their purpose and expected values.

Example:

```php
/**
 * @description This class handles the validation of user passwords.
 */
class PasswordStrengthRule implements Rule
{
    /**
     * @description Determine if the validation rule passes.
     * 
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Implementation
    }

    /**
     * @description Get the validation error message.
     * 
     * @return string
     */
    public function message()
    {
        // Implementation
    }
}
```

## PHP-Parser Integration

We use PHP-Parser for robust and flexible code analysis. Here's why:

1. **Accuracy**: PHP-Parser provides a complete Abstract Syntax Tree (AST) of the PHP code, allowing for more accurate and detailed analysis compared to regex-based solutions.

2. **Flexibility**: It can handle complex PHP structures and syntax, making our documentation more comprehensive.

3. **Maintainability**: Using a standardized parsing library makes the code easier to maintain and extend.

4. **Future-proofing**: PHP-Parser is regularly updated to support new PHP features, ensuring our tool can document even the most modern PHP code.

## Extending the Package

To extend Laravel Documenter, you can:

1. **Modify the Documenters**: 
   - Update any of the documenter classes to change how specific components are documented
   - Utilize PHP-Parser's NodeVisitor pattern for complex analysis

2. **Enhance the Main Class**:
   - Add new methods to `LaravelDocumenter.php` for additional functionality

3. **Expand the Command**:
   - Modify `GenerateDocumentation.php` to add new options or behaviors

4. **Customize the Stubs**:
   - Modify the Blade stubs in `resources/views/vendor/laravel-documenter` to change the output format of the documentation

## Development Workflow

1. Fork the repository
2. Create a new branch for your feature or bug fix
3. Write tests for your changes
4. Implement your changes
5. Ensure all tests pass
6. Submit a pull request with a clear description of your changes

## Coding Standards

- Follow PSR-12 coding standard
- Write clear, self-documenting code
- Include comprehensive DocBlocks for all classes and methods
- Write unit tests for new functionality
- When working with PHP-Parser, follow their conventions for node traversal and manipulation

## Testing

The package uses PHPUnit for testing. To run the tests:

```bash
composer test
```

Ensure all tests pass before submitting a pull request.

## Documentation

When adding new features or making significant changes:
1. Update the README.md file if necessary
2. Add or update DocBlocks in the code
3. If adding new configuration options, update the config file and its documentation
4. If changing the documentation format, update the relevant Blade stubs

## Submitting Changes

1. Push your changes to your fork
2. Submit a pull request to the main repository
3. Clearly describe your changes and their motivations
4. Reference any related issues

## Questions or Suggestions?

If you have any questions about contributing or ideas for improvements, please open an issue in the GitHub repository.

Thank you for contributing to Laravel Documenter!