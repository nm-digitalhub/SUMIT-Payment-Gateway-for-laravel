# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **BREAKING**: Upgraded Filament dependency from v3 to v4
- **BREAKING**: Minimum Laravel version increased to 11.28 (required by Filament v4)
- Updated `orchestra/testbench` to ^9.0 for Laravel 11 compatibility
- Updated `phpunit/phpunit` to support ^10.0|^11.0
- Updated package description to mention Filament v4 support

### Added
- Added comprehensive upgrade guide (docs/UPGRADE_TO_V4.md)
- Added upgrade notice banner to README.md

### Notes
- This package does not currently implement any Filament resources, so the upgrade only affects dependency versions
- No code changes are required for existing users (only dependency updates)
- All existing payment gateway functionality remains unchanged
- Future Filament resources will be built using Filament v4 architecture

## [1.0.0] - Initial Release

### Added
- Complete payment gateway integration for SUMIT/OfficeGuy platform
- Card payment support (PCI direct, redirect, and simple modes)
- Bit payment support via Upay
- Tokenization for secure card storage and recurring payments
- Automatic invoice and receipt creation
- Multi-currency support (36+ currencies)
- Installment payment plans with custom rules
- Comprehensive logging and debugging tools
- VAT calculation and document generation
- Callback and webhook handling
- Database models for transactions, tokens, and documents
- Service classes for API communication
- Blade components for payment forms
- Full configuration via environment variables
- Comprehensive documentation

### Features
- 1:1 port from official WooCommerce plugin (v3.3.1)
- Polymorphic relationships for flexibility
- Service-oriented architecture
- Laravel 10.x and 11.x support (before v4 upgrade)
- PHP 8.2+ support
- Eloquent ORM integration
- Laravel HTTP client for API calls
- Extensive inline documentation

[Unreleased]: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel/releases/tag/v1.0.0
