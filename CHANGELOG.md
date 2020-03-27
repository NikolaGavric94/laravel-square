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

## [1.1.0] - 2017-10-05
### Added
- Support for [nikolag/core](https://github.com/NikolaGavric94/nikolag-core/) package

### Changed
- Code cleanup
- Structure of `nikolag.php` configuration file
- Removed migration files, they are now in core package
- Renamed `SquareCustomer` to `SquareService`
- Renamed `SquareContract` to `SquareServiceContract`
- Changed input parameters for `charge()` and `transactions()` functions on `SquareServiceContract`

### Fixed
- Problem with custom exception handler

## [1.1.1] - 2017-11-06
### Fixed
- Resolved [#12](https://github.com/NikolaGavric94/laravel-square/issues/12)

## [2.0.0] - 2018-04-07
### Added
- Order system
- Products system
- Taxes system
- Discounts system
- Test coverage
- Wiki pages

### Changed
- Updated README.md
- Code cleanup
- Migrations

## [2.0.1] - 2018-04-16
### Added
- [Simple Examples](https://github.com/NikolaGavric94/laravel-square/wiki/Simple%20Examples)

### Changed
- README.md

### Fixed
- Fixed issue #16 

## [2.1.0] - 2018-06-17
### Added
Wiki pages:
- [Installation guide](https://github.com/NikolaGavric94/laravel-square/wiki/Installation%20guide)
- [Square Facade](https://github.com/NikolaGavric94/laravel-square/wiki/Square%20Facade)
- [Transaction](https://github.com/NikolaGavric94/laravel-square/wiki/Transactions)
- [HasProducts Trait](https://github.com/NikolaGavric94/laravel-square/wiki/HasProducts%20Trait)
- [HasCustomers Trait](https://github.com/NikolaGavric94/laravel-square/wiki/HasCustomers%20Trait)

Transaction fields:
- currency
- payment_service_id

### Changed
- README.md
- Wiki pages
- Code cleanup
- Renamed the project to `nikolag/laravel-square`

### Fixed
- Order no longer requires `payment_service_type` to be added inside `attributes`

## [2.1.1] - 2018-06-20
### Added
- Bigger unit test coverage

### Changed
- README.md

### Fixed
- Proper resolving of passed options for `transactions` method inside `SquareService`
- `charge` method on `HasProducts` trait to now properly pass in location id

## [2.1.2] - 2018-08-29
### Added
- `Customer` builder

### Changed
- `SquareService` code cleanup

## [2.2.0] - 2018-08-30
### Added
- `Laravel 5.7` and `Lumen >= 5.5` integration
- `Lumen` and `Laravel` integration tests
- Latest `square/connect` version (2.9)
- Latest `orchestra/testbench` version (3.7) 

### Changed
- Improved `TravisCI` configuration
- Updated `CodeClimate` test reporter
- Improved test coverage
- Improved environment test coverage

### Fixed
- Removed deprecated `CodeClimate` test reporter
- Test report triggers on `CodeClimate` are not per commit

## [2.2.1] - 2019-09-21
### Fixed
- Fixed issue #26

## [2.3.0] - 2019-09-24
### Added
- `Laravel 5.8` and `Lumen 5.8` support
- `Laravel 6` support
- Latest `orchestra/testbench` version (4.*)

### Changed
- Improved `TravisCI` configuration

## [2.4.0] - 2019-10-14
### Added
- Latest `Square API` version (2.20190925.0)
- Sandbox environment flag

### Changed
- Deprecated and removed used nonce exception
- Taxes, discounts and line items in payments not a top level children anymore https://developer.squareup.com/reference/square/orders-api/create-order#request__property-discounts

## [2.4.1] - 2019-10-23
### Added
- `location_id`, `note` and `reference_id` into [charge](https://github.com/NikolaGavric94/laravel-square/blob/5136c3fafe4a713f31e70c72d75f3e8ac0ecd8a5/src/SquareService.php#L220-L238) method

### Changed
- `charge` method signature for both [HasProducts](https://github.com/NikolaGavric94/laravel-square/wiki/HasProducts%20Trait#hasproducts-trait)
and [HasCustomers](https://github.com/NikolaGavric94/laravel-square/wiki/HasCustomers%20Trait#hascustomers-trait)
trait
- **BREAKING CHANGE:** `charge` method when using [facade](https://github.com/NikolaGavric94/laravel-square/wiki/Simple%20Examples)
approach now requires `location_id`
to be present in the `$options` array
- **BREAKING CHANGE:** `charge` method signature for 
[Customer system](https://github.com/NikolaGavric94/laravel-square/wiki/Customer%20Examples)
on both 
[trait](https://github.com/NikolaGavric94/laravel-square/wiki/Customer%20Examples#trait-approach-1)
and [facade](https://github.com/NikolaGavric94/laravel-square/wiki/Customer%20Examples#facade-approach-1)
approach
- **BREAKING CHANGE:** `charge` method signature for 
[Order system](https://github.com/NikolaGavric94/laravel-square/wiki/Order%20Examples)
on both 
[trait](https://github.com/NikolaGavric94/laravel-square/wiki/Order%20Examples#trait-approach)
and [facade](https://github.com/NikolaGavric94/laravel-square/wiki/Order%20Examples#facade-approach-1)
approach

## [2.4.2] - 2020-03-09
### Added
- `buildOrderModelFromArray` into the [OrderBuilder](https://github.com/NikolaGavric94/laravel-square/blob/2ace8e77e70707526f6079688a2099b42be91656/src/builders/OrderBuilder.php#L235-L260)

### Changed
- [setOrder](https://github.com/NikolaGavric94/laravel-square/blob/2ace8e77e70707526f6079688a2099b42be91656/src/SquareService.php#L469-L470) now properly assigns property values (no more mass assignments).
- [Order system examples](https://github.com/NikolaGavric94/laravel-square/wiki/Order%20Examples) are now updated with proper examples
- [README.md](https://github.com/NikolaGavric94/laravel-square/blob/master/README.md) section with `Orders system` is now updated to include `$table` property

### Fixed
- Resolved [#37](https://github.com/NikolaGavric94/laravel-square/issues/37)

## [2.5.0] - 2020-03-26
### Added
- Support for `Laravel 7.x`

### Changed
- [README.md](https://github.com/NikolaGavric94/laravel-square/blob/master/README.md) section with `Version Compatibility` is now updated to include `Laravel 7.x`

### Fixed
- Resolved [#41](https://github.com/NikolaGavric94/laravel-square/issues/41)
- Resolved [#40](https://github.com/NikolaGavric94/laravel-square/issues/40)

[Unreleased]: https://github.com/NikolaGavric94/laravel-square/compare/v2.5.0...HEAD
[1.0.1]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.0...v1.0.1
[1.0.2]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.1...v1.0.2
[1.0.3]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.2...v1.0.3
[1.0.4]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.3...v1.0.4
[1.1.0]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.4...v1.1.0
[1.1.1]: https://github.com/NikolaGavric94/laravel-square/compare/v1.0.0...v1.1.1
[2.0.0]: https://github.com/NikolaGavric94/laravel-square/compare/v1.1.1...v2.0.0
[2.0.1]: https://github.com/NikolaGavric94/laravel-square/compare/v2.0.0...v2.0.1
[2.1.0]: https://github.com/NikolaGavric94/laravel-square/compare/v2.0.1...v2.1.0
[2.1.1]: https://github.com/NikolaGavric94/laravel-square/compare/v2.1.0...v2.1.1
[2.1.2]: https://github.com/NikolaGavric94/laravel-square/compare/v2.1.1...v2.1.2
[2.2.0]: https://github.com/NikolaGavric94/laravel-square/compare/v2.1.2...v2.2.0
[2.2.1]: https://github.com/NikolaGavric94/laravel-square/compare/v2.2.0...v2.2.1
[2.3.0]: https://github.com/NikolaGavric94/laravel-square/compare/v2.2.1...v2.3.0
[2.4.0]: https://github.com/NikolaGavric94/laravel-square/compare/v2.3.0...v2.4.0
[2.4.1]: https://github.com/NikolaGavric94/laravel-square/compare/v2.4.0...v2.4.1
[2.4.2]: https://github.com/NikolaGavric94/laravel-square/compare/v2.4.1...v2.4.2
[2.5.0]: https://github.com/NikolaGavric94/laravel-square/compare/v2.4.2...v2.5.0
