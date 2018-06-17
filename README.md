laravel-square 
[![Build Status](https://travis-ci.org/NikolaGavric94/laravel-square.svg?branch=master)](https://travis-ci.org/NikolaGavric94/laravel-square)
[![Latest Stable Version](https://poser.pugx.org/laravel-square/v/stable)](https://packagist.org/packages/laravel-square) 
[![Total Downloads](https://poser.pugx.org/laravel-square/downloads)](https://packagist.org/packages/laravel-square) 
[![License](https://poser.pugx.org/laravel-square/license)](https://packagist.org/packages/laravel-square) 
=========
Square integration with Laravel >=5.5 built on [nikolag/core](https://github.com/NikolaGavric94/nikolag-core/)

1.  [Installation guide](#installation-guide) 
2.  [Customers System](#customers-system) 
3.  [Order System](#order-system) 
4.  [Examples](#examples) 
5.  [Available methods](#all-available-methods) 
6.  [Contributing](#contributing) 
7.  [License](#license)

## Donating [![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://paypal.me/NikolaGavric/25)

Any amount helps to dedicate more time and resources for developing new stuff and keeping the library up-to-date with both `Laravel` and `Square` changes in the future. It will also help in creating future projects under the same brand.

## Installation guide
`composer require laravel-square`

**Note:** Due to Laravel [Package Discovery](https://laravel.com/docs/5.6/packages#package-discovery), registering service providers and facades manually for this project as of Laravel 5.5 is deprecated and no longer required since the package is adapted to automatically register these stuff for you.
But there are still couple of steps to do in order to use this package.

---

Configuration files will automatically be published for you and you should check it out at `config/nikolag.php` before continuing.

**Important:** If for some reason you can't see `square` driver inside of `connections` array, you'll have to add it manually. You can find configuration file [here](https://github.com/NikolaGavric94/laravel-square/blob/master/src/config/nikolag.php) and copy everything from inside `connections` array and **append** to your `connections` array inside of published config file (`config/nikolag.php`)

<p align="center" style="text-align: center;">
  <img src="https://preview.ibb.co/ddoUGS/nikolag_config.png" alt="nikolag configuration" title="Nikolag Configuration File" />
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
To be able to utilize the order system for Users, your Order class must use `HasProducts` trait.
```php
...
use Nikolag\Square\Traits\HasProducts;

class Order extends Model {
  use HasProducts;
  ...
}
```
You also need to define couple of environment variables
```.env
// .env file
SQUARE_ORDER_NAMESPACE=<ORDER_NAMESPACE>
SQUARE_ORDER_IDENTIFIER=<ORDER_IDENTIFIER>
SQUARE_PAYMENT_IDENTIFIER=<ORDER_SQUARE_ID_COLUMN>
```
**Important:** `SQUARE_PAYMENT_IDENTIFIER` represents name of the column where we will keep unique ID that Square generates once it saves an Order. This means that you will need to add new column to your Orders table which will hold that value.

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
enquiries send an email to nikola.gavric94@gmail.com

### Contributors
| Name                                               | Changes                                                                                                                       | Date       |
| -------------------------------------------------- |:-----------------------------------------------------------------------------------------------------------------------------:|:----------:|
| [@Godlikehobbit](https://github.com/Godlikehobbit) | Add optional currency parameter to charge function [pull request #6](https://github.com/NikolaGavric94/laravel-square/pull/6) | 2017-09-12 |

## License
MIT License

Copyright (c) 2018

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
