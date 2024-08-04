# Contributing to {{ $projectName }}

Thank you for your interest in contributing to {{ $projectName }}. This document provides an overview of our project structure and guidelines for contribution.

## Generated Documentation

The following documentation files have been generated:

@foreach($documenters as $documenter)
- [{{ ucfirst($documenter) }} Documentation](./CONTRIBUTING.{{ strtoupper($documenter) }}.md)
@endforeach

Please refer to these files for detailed information about different components of our project.

## How to Contribute

{{-- Replace this comment with your contribution guidelines --}}

## Coding Standards

{{-- Replace this comment with your coding standards --}}

## Questions or Suggestions?

If you have any questions about contributing or ideas for improvements, please open an issue in our GitHub repository.

Thank you for contributing to {{ $projectName }}!