[![Test Coverage](https://api.codeclimate.com/v1/badges/7b6c53096c35381463c5/test_coverage)](https://codeclimate.com/github/NikolaGavric94/laravel-square/test_coverage)
[![Build and test PHP 8.x](https://github.com/NikolaGavric94/laravel-square/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/NikolaGavric94/laravel-square/actions/workflows/main.yml)
[![Latest Stable Version](https://poser.pugx.org/nikolag/laravel-square/v/stable)](https://packagist.org/packages/nikolag/laravel-square) 
[![License](https://poser.pugx.org/nikolag/laravel-square/license)](https://packagist.org/packages/nikolag/laravel-square)
<p><a href="https://medium.com/square-corner-blog/square-implementation-with-laravel-22a4ad3fe1ec">
    <img src="https://miro.medium.com/max/1920/1*84QaUM_X3hvpO8qe9b_5jw.png" title="Nikolag Laravel Package" />
</a></p>

-------------------------

Square integration with Laravel/Lumen >=5.5 built on [nikolag/core](https://github.com/NikolaGavric94/nikolag-core/)

1. [Version Compatibility](#version-compatibility)
2. [Installation guide](#installation-guide) 
3. [Customer System](#customers-system) 
4. [Order System](#orders-system) 
5. [Examples](#examples) 
6. [Available methods](#all-traits-and-their-methods)  
7. [Contributing](#contributing) 
8. [License](#license)


## Version Compatibility

### Old square connect api
| Library Version 	| Laravel Version 	| Square Connect Version                   	|
|:-----------------:|:-------------------------:|:---------------------------------:|
| [2.3.x](https://github.com/NikolaGavric94/laravel-square/compare/v2.3.0...master) |&nbsp; >= 5.5 | [2.20190814 (Square Connect V1)](https://github.com/square/connect-php-sdk/tree/2.20190710.0)   |
| [2.4.x](https://github.com/NikolaGavric94/laravel-square/compare/v2.4.0...master) |&nbsp; >= 6.x | [2.20190925.0 (Square Connect V2)](https://github.com/square/connect-php-sdk/tree/2.20190925.0) |
| [2.5.x](https://github.com/NikolaGavric94/laravel-square/compare/v2.5.0...master) |&nbsp; >= 7.x | [2.20190925.0 (Square Connect V2)](https://github.com/square/connect-php-sdk/tree/2.20190925.0) |
| [2.6.x](https://github.com/NikolaGavric94/laravel-square/compare/v2.6.0...master) |&nbsp; >= 8.x | [3.20200528.1 (Square Connect V2)](https://github.com/square/connect-php-sdk/tree/3.20200528.1) |

### New square sdk
|                                 Library Version 	                                 | Laravel Version 	 | Square SDK Version                   	|
|:---------------------------------------------------------------------------------:|:-----------------:|:---------------------------------:|
| [3.0.x](https://github.com/NikolaGavric94/laravel-square/compare/v2.6.0...v3.0.2) |   &nbsp; >= 8.x   | [17.0.0.20211215](https://github.com/square/square-php-sdk/tree/17.0.0.20211215)   |
| [3.1.x](https://github.com/NikolaGavric94/laravel-square/compare/v3.0.2...v3.1.0) |   &nbsp; >= 9.x   | [25.1.0.20230119](https://github.com/square/square-php-sdk/tree/25.1.0.20230119)   |
| [3.2.0](https://github.com/NikolaGavric94/laravel-square/compare/v3.2.0...3.2.1) |  &nbsp; >= 10.x   | [25.1.0.20230119](https://github.com/square/square-php-sdk/tree/25.1.0.20230119)   |
| [3.2.x](https://github.com/NikolaGavric94/laravel-square/compare/v3.2.1...v3.2.) |  &nbsp; >= 10.x   | [29.0.0.20230720](https://github.com/square/square-php-sdk/tree/29.0.0.20230720)   |
| [3.3.x](https://github.com/NikolaGavric94/laravel-square/compare/v3.2.1...v3.3.0) |  &nbsp; >= 10.x   | [29.0.0.20230720](https://github.com/square/square-php-sdk/tree/29.0.0.20230720)   |
| [3.4.0](https://github.com/NikolaGavric94/laravel-square/compare/v3.3.0...v3.4.0) |  &nbsp; >= 11.x   | [29.0.0.20230720](https://github.com/square/square-php-sdk/tree/29.0.0.20230720)   |
| [3.4.x](https://github.com/NikolaGavric94/laravel-square/compare/v3.4.0...master) |  &nbsp; >= 11.x   | [40.0.0.20250123](https://github.com/square/square-php-sdk/tree/40.0.0.20250123)   |

**If you are updating from versions below 3.0 then you need to execute: `php artisan migrate`. This will add some columns required by the library into the tables created by the library, your own tables won't be affected.**

## Installation guide
`composer require nikolag/laravel-square`

**Note:** Due to Laravel [Package Discovery](https://laravel.com/docs/5.6/packages#package-discovery), registering service providers and facades manually for this project as of Laravel 5.5 is deprecated and no longer required since the package is adapted to automatically register these stuff for you.
But there are still couple of steps to do in order to use this package.

---

First you have to publish configuration files:
```php
php artisan vendor:publish --tag=nikolag_config
```
Check configuration files out at `config/nikolag.php` before continuing.

**Important:** If for some reason you can't see `square` driver inside of `connections` array, you'll have to add it manually. You can find configuration file [here](https://github.com/NikolaGavric94/laravel-square/blob/master/src/config/nikolag.php) and copy everything from inside `connections` array and **append** to your `connections` array inside of published config file (`config/nikolag.php`)

<p align="center" style="text-align: center;">
  <img src="https://i.ibb.co/vsBCZtJ/Screenshot-2019-10-14-at-10-50-52.png" alt="nikolag configuration" title="Nikolag Configuration File" />
  <br>
  <i>Figure 1. Config file</i>
</p>

After finishing with configuration files, you should run migrations with the following command
```php
php artisan migrate
```

---

Then add your credentials for Square API inside of `.env` and also add fully qualified name (namespace) for your classes.
```.env
SQUARE_APPLICATION_ID=<YOUR_APPLICATION_ID>
SQUARE_TOKEN=<YOUR_ACCESS_TOKEN>
```

### Customers system
To be able to utilize the customers system for Users, your User class must use `HasCustomers` trait.
```php
...
use Nikolag\Square\Traits\HasCustomers;

class User extends Model {
  use HasCustomers;
  ...
}
```

You also need to define user namespace
```.env
// .env file
SQUARE_USER_NAMESPACE=<USER_NAMESPACE>
```

### Orders system
To be able to utilize the order system for Users, your Order class must use `HasProducts` trait and define `$table` property.
```php
...
use Nikolag\Square\Traits\HasProducts;

class Order extends Model {
  use HasProducts;
  ...

  /**
   * The table associated with the model.
   *
   * @var string
   */
  protected $table = '<TABLE_NAME>';
  ...
}
```
You also need to define a couple of environment variables
```.env
// .env file
SQUARE_ORDER_NAMESPACE=<ORDER_NAMESPACE>
SQUARE_ORDER_IDENTIFIER=<ORDER_IDENTIFIER>
SQUARE_PAYMENT_IDENTIFIER=<ORDER_SQUARE_ID_COLUMN>
```
**Important:** `SQUARE_PAYMENT_IDENTIFIER` represents the name of the column where we will keep the unique ID that Square generates once it saves an Order. This means that you will need to add a new column to your Orders table which will hold that value.

## Examples

##### Simple examples
Simple examples are moved to [wiki](https://github.com/NikolaGavric94/laravel-square/wiki/Simple%20Examples) pages to avoid unnecessary scrolling of `README.md`.

##### Examples with customers
Examples with customers are moved to [wiki](https://github.com/NikolaGavric94/laravel-square/wiki/Customer%20Examples) pages to avoid unnecessary scrolling of `README.md`.

##### Examples with order
Examples with orders are moved to the [wiki](https://github.com/NikolaGavric94/laravel-square/wiki/Order%20Examples) pages to avoid unnecessary scrolling of `README.md`.

## All traits and their methods

##### HasProducts
All methods for this trait are moved to the [wiki](https://github.com/NikolaGavric94/laravel-square/wiki/HasProducts%20Trait) pages to avoid unnecessary scrolling of `README.md`.

##### HasCustomers
All methods for this trait are moved to the [wiki](https://github.com/NikolaGavric94/laravel-square/wiki/HasCustomers%20Trait) pages to avoid unnecessary scrolling of `README.md`.

### All facades and their methods

##### Square
All methods for this facade are moved to the [wiki](https://github.com/NikolaGavric94/laravel-square/wiki/Square%20Facade) pages to avoid unnecessary scrolling of `README.md`.

## Contributing
Everyone is welcome to contribute to this repository, simply open up an issue
and label the request, whether it is an issue, bug or a feature. For any other
inquiries send an email to nikola.gavric94@gmail.com

### Contributors
| Name                                               | Changes                                                                                                                       | Date       |
| -------------------------------------------------- |:-----------------------------------------------------------------------------------------------------------------------------:|:----------:|
| [@Godlikehobbit](https://github.com/Godlikehobbit) | Add optional currency parameter to charge function [pull request #6](https://github.com/NikolaGavric94/laravel-square/pull/6) | 2017-09-12 |
| [@PaulJulio](https://github.com/PaulJulio) | Cap square/connect version to resolve deprecation exceptions [pull request #27](https://github.com/NikolaGavric94/laravel-square/pull/27) | 2019-09-20 |
| [@Alex Oleynik](https://github.com/alexoleynik0) | Fixes TypeError when updating customers [pull request #71](https://github.com/NikolaGavric94/laravel-square/pull/71) | 2022-06-28 |
| [@qooplmao](https://github.com/qooplmao) | Adds support for 3DS/SCA [pull request #74](https://github.com/NikolaGavric94/laravel-square/pull/74) | 2023-01-13 |
| [@mrkbingham](https://github.com/mrkbingham) | Adds support for Fulfillments [pull request #74](https://github.com/NikolaGavric94/laravel-square/pull/88) | 2025-06-27 |

Special thanks to all of the contributors!

## License
MIT License

Copyright (c) Nikola Gavrić <nikola.gavric94@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
