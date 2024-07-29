# Contributing to Laravel Documenter

Thank you for your interest in contributing to Laravel Documenter! This document provides an overview of the project's architecture and guidelines for extension and contribution.

## Project Architecture

Laravel Documenter is structured as a Laravel package, designed to generate comprehensive documentation for Laravel and Filament projects. Here's an overview of the key components:

### 1. Service Provider

`LaravelDocumenterServiceProvider.php` is the entry point of the package. It:
- Registers the package's config file
- Binds the main `LaravelDocumenter` class to the service container
- Registers individual documentation generators

### 2. Main Class

`LaravelDocumenter.php` orchestrates the documentation generation process. It:
- Coordinates between different generators
- Handles high-level logic for documentation generation

### 3. Command

`GenerateDocumentation.php` is an Artisan command that:
- Provides a CLI interface for generating documentation
- Handles user input for specifying components or custom output paths

### 4. Generators

Located in the `Generators/` directory, each generator is responsible for documenting a specific component type:
- `ModelDocumenter.php`
- `FilamentResourceDocumenter.php`
- `ApiControllerDocumenter.php`
- `JobDocumenter.php`
- `EventDocumenter.php`
- `MiddlewareDocumenter.php`
- `RuleDocumenter.php`

### 5. Configuration

`config/laravel-documenter.php` allows users to customize the behavior of the package.

### 6. Directory Structure

The Laravel Documenter package is organized into the following main directories:

- `src/`: Contains the core source code of the package.
  - `Commands/`: Holds the Artisan commands, including `GenerateDocumentation.php`.
  - `Generators/`: Contains individual generator classes for each component type.
  - `Stubs/`: Stores stub files used as templates for generating documentation.
- `config/`: Contains the configuration file `laravel-documenter.php`.
- `tests/`: Holds the PHPUnit test files for the package.

Understanding this structure is crucial when contributing to or extending the package.

## DocBlocks

DocBlocks are crucial for the proper functioning of Laravel Documenter. When contributing to this project or using it in your own projects, please ensure that your code includes comprehensive DocBlocks. Here are some guidelines:

1. Every class should have a DocBlock with a `@description` tag explaining its purpose.
2. Methods, especially key methods like `handle()` in Jobs and Middleware, or `passes()` in Rules, should have DocBlocks explaining their functionality.
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

## Extending the Package

To extend Laravel Documenter, you can:

1. **Add a New Generator**: 
   - Create a new class in the `Generators/` directory
   - Implement the generation logic
   - Register the new generator in `LaravelDocumenterServiceProvider.php`

2. **Modify Existing Generators**:
   - Each generator can be extended or replaced to customize documentation output

3. **Enhance the Main Class**:
   - Add new methods to `LaravelDocumenter.php` for additional functionality

4. **Expand the Command**:
   - Modify `GenerateDocumentation.php` to add new options or behaviors

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

## Submitting Changes

1. Push your changes to your fork
2. Submit a pull request to the main repository
3. Clearly describe your changes and their motivations
4. Reference any related issues

## Questions or Suggestions?

If you have any questions about contributing or ideas for improvements, please open an issue in the GitHub repository.

Thank you for contributing to Laravel Documenter!