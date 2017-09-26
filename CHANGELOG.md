# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2017-07-10
### Added
- Charging a customer
- Saving customer
- Listing customers
- Listing transactions
- Saving transactions
- Created trait which utilizes same methods like Square facade

## [1.0.1] - 2017-09-12
### Added
- Ability to change currencies thanks to [pull request #6](https://github.com/NikolaGavric94/laravel-square/pull/6) by [@Godlikehobbit](https://github.com/Godlikehobbit)
- Tests for changing currency

### Changed
- Fixed rollback trait which caused travis cli build to fail
- Fixed charge method in HasCustomer, had some redundant code
- Changed credentials for square api

## [1.0.2] - 2017-09-25
### Changed
- Upgraded to work with Laravel 5.5.x

### Fixed
- Resolved [#7](https://github.com/NikolaGavric94/laravel-square/issues/7)

## [1.0.3] - 2017-09-26
### Added
- Automatic registration of package service providers and facades

### Fixed
- Resolved [#8](https://github.com/NikolaGavric94/laravel-square/issues/8)

## [1.0.4] - 2017-09-26
### Added
- Missing methods for getting transactions by status

### Changed
- Removed local scope methods for getting transactions by status
- Updated project documentation

[Unreleased]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.4...HEAD
[1.0.1]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.0...v1.0.1
[1.0.2]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.1...v1.0.2
[1.0.3]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.2...v1.0.3
[1.0.3]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.3...v1.0.4
