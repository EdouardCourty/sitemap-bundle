# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-08

 Initial release.

### Features
- Static routes support
- Dynamic Doctrine entity routes
- Custom URL providers via `UrlProviderInterface`
- Sitemap index for large datasets (>50k URLs)
- Memory-efficient streaming
- Dynamic generation (`/sitemap.xml`) and static command (`sitemap:dump`)
- Symfony 6.4+ / 7.0+ / 8.0+ compatibility
- Doctrine ORM 3.0+ / 4.0+ compatibility
