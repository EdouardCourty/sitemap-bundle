# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial implementation of sitemap bundle
- Support for static routes
- Support for dynamic routes with Doctrine entities
- Custom URL provider support via `UrlProviderInterface` for complex use cases (CMS pages, external data sources)
- Sitemap index support for large sitemaps (>50k URLs)
- Dynamic generation via controller (`/sitemap.xml`)
- Static generation via command (`sitemap:dump`)
- Flexible YAML configuration
- Repository method support for custom queries
- DQL conditions support for simple filtering
- Memory-efficient entity streaming with `toIterable()`
- Comprehensive exception hierarchy
- PHPStan Level 9 compliance
- PSR-12 code style
- PHP 8.3+ support with strict types
- Symfony 6.4+ / 7.0+ / 8.0+ compatibility
- Doctrine ORM 3.0+ / 4.0+ compatibility

### Tests
- Integration tests for custom URL provider implementation
- Tests for CMS-like scenarios with dynamic routing based on page types
- Memory efficiency tests for large datasets (1000+ URLs)
- Tests for priority and changefreq stored in database

[Unreleased]: https://github.com/ecourty/sitemap-bundle/compare/v1.0.0...HEAD
