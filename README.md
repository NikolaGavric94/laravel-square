nikolag/square 
[![Build Status](https://travis-ci.org/NikolaGavric94/nikolag-square.svg?branch=develop)](https://travis-ci.org/NikolaGavric94/nikolag-square)
[![Latest Stable Version](https://poser.pugx.org/nikolag/square/v/stable)](https://packagist.org/packages/nikolag/square) 
[![Total Downloads](https://poser.pugx.org/nikolag/square/downloads)](https://packagist.org/packages/nikolag/square) 
[![License](https://poser.pugx.org/nikolag/square/license)](https://packagist.org/packages/nikolag/square) 
=========
Square integration with Laravel 5.6.x built on [nikolag/core](https://github.com/NikolaGavric94/nikolag-core/)

## Installation guide
`composer require nikolag/square`

**Note:** Due to Laravel [Package Discovery](https://laravel.com/docs/5.6/packages#package-discovery), registering service providers and facades manually for this project as of Laravel 5.5 is deprecated and no longer required since the package is adapted to automatically register these stuff for you.
But there are still couple of steps to do in order to use this package.

---

Configuration files will automatically be published for you and you should check it out at `config/nikolag.php` before continuing.

**Important:** If for some reason you can't see `square` driver inside of `connections` array, you'll have to add it manually. You can find configuration file [here](https://github.com/NikolaGavric94/nikolag-square/blob/master/src/config/nikolag.php) and copy everything from inside `connections` array and **append** to your `connections` array inside of published config file (`config/nikolag.php`)

<p align="center" style="text-align: center;">
  <img src="https://preview.ibb.co/ddoUGS/nikolag_config.png" alt="nikolag configuration" title="Nikolag Configuration File" />
  <br>
  <i>Figure 1. Config file</i>
</p>

After finishing with configuration files, you should run migrations with the following command
```javascript
php artisan migrate
```

---

Then add your credentials for Square API inside of `.env` and also add fully qualified name (namespace) for your classes.
```javascript
SQUARE_APPLICATION_ID=<YOUR_APPLICATION_ID>
SQUARE_TOKEN=<YOUR_ACCESS_TOKEN>
```

### Customers system
To be able to utilize the customers system for Users, your User class must use `HasCustomers` trait.
```javascript
<?php
...
use Nikolag\Square\Traits\HasCustomers;

class User extends Model {
  use HasCustomers;
  ...
}
```

You also need to define user namespace
```javascript
// .env file
SQUARE_USER_NAMESPACE=<USER_NAMESPACE>
```

### Orders system
To be able to utilize the order system for Users, your Order class must use `HasProducts` trait. You will also have to create a column named `payment_service_type` in your `orders` table and add the below code from `attributes` array
```javascript
<?php
...
use Nikolag\Square\Traits\HasProducts;

class Order extends Model {
  use HasProducts;

  /**
   * The model's attributes.
   *
   * @var array
   */
  protected $attributes = [
    'payment_service_type' => 'square'
  ];
  ...
}
```
You also need to define couple of environment variables
```javascript
// .env file
SQUARE_ORDER_NAMESPACE=<ORDER_NAMESPACE>
SQUARE_ORDER_IDENTIFIER=<ORDER_IDENTIFIER>
SQUARE_PAYMENT_IDENTIFIER=<ORDER_SQUARE_ID_COLUMN>
```
**Important:** `SQUARE_PAYMENT_IDENTIFIER` represents name of the column where we will keep unique ID that Square generates once it saves an Order. This means that you will need to add new column to your Orders table which will hold that value.

## Examples

##### Examples with customers
Examples with customers are moved to [wiki](https://github.com/NikolaGavric94/nikolag-square/wiki/Customer%20Examples) pages to avoid unnecessary scrolling of `README.md`.

##### Examples with order
Examples with orders are moved to the [wiki](https://github.com/NikolaGavric94/nikolag-square/wiki/Order%20Examples) pages to avoid unnecessary scrolling of `README.md`.

## All available methods
#### HasProducts Trait
```javascript
/**
 * Charge an order.
 *
 * @param float $amount
 * @param string $nonce
 * @param string $location_id
 * @param mixed $merchant
 * @param mixed $customer
 * @param string $currency
 * 
 * @return \Nikolag\Square\Models\Transaction
 */
public function charge(float $amount, string $nonce, string $location_id, $merchant, $customer = null, string $currency = 'USD') {}

/**
 * Check existence of an attribute in model
 *
 * @param string $attribute
 * 
 * @return bool
 */
public function hasAttribute(string $attribute) {}

/**
 * Does an order have a discount
 *
 * @param mixed $discount
 * 
 * @return bool
 */
public function hasDiscount($discount) {}

/**
 * Does an order have a tax
 *
 * @param mixed $tax
 * 
 * @return bool
 */
public function hasTax($tax) {}

/**
 * Does an order have a product
 *
 * @param mixed $product
 * 
 * @return bool
 */
public function hasProduct($product) {}

/**
 * Return a list of products which are included in this order.
 *
 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
 */
public function products() {}

/**
 * Return a list of taxes which are in included in this order.
 *
 * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
 */
public function taxes() {}

/**
 * Return a list of discounts which are in included in this order.
 *
 * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
 */
public function discounts() {}
```

#### HasCustomers Trait
```javascript
/**
 * Retrieve merchant customers.
 *
 * @return \Illuminate\Database\Eloquent\Relations\HasMany
 */
public function customers() {}

/**
 * Retrieve customer if he exists, otherwise return false.
 *
 * @param string $email
 *
 * @return mixed
 */
public function hasCustomer(string $email) {}

/**
 * All transactions.
 *
 * @return \Illuminate\Database\Eloquent\Relations\HasMany
 */
public function transactions() {}

/**
 * Paid transactions.
 *
 * @return \Illuminate\Database\Eloquent\Collection
 */
public function passedTransactions() {}

/**
 * Pending transactions.
 *
 * @return \Illuminate\Database\Eloquent\Collection
 */
public function openedTransactions() {}

/**
 * Failed transactions.
 *
 * @return \Illuminate\Database\Eloquent\Collection
 */
public function failedTransactions() {}

/**
 * Charge a customer.
 *
 * @param float $amount
 * @param string $nonce
 * @param string $location_id
 * @param mixed $customer
 * @param string $currency
 * 
 * @return \Nikolag\Square\Models\Transaction
 */
public function charge(float $amount, string $nonce, string $location_id, $customer = null, string $currency = 'USD') {}

/**
 * Save a customer.
 *
 * @param array $customer
 * 
 * @return void
 */
public function saveCustomer(array $customer) {}
```
### Facade
```javascript
/**
 * Charge a customer.
 *
 * @param array $data
 *
 * @return \Nikolag\Square\Models\Transaction
 * @throws \Nikolag\Square\Exception on non-2xx response
 */
public function charge(array $data) {}

/**
 * Save collected data
 *
 * @return self
 * @throws \Nikolag\Square\Exception on non-2xx response
 */
public function save() {}

/**
 * Transactions directly from Square API.
 *
 * @param array $options
 *
 * @return \SquareConnect\Model\ListLocationsResponse
 * @throws \Nikolag\Square\Exception on non-2xx response
 */
public function transactions(array $options) {}

/**
 * Add a product to the order.
 *
 * @param mixed $product
 * @param int $quantity
 * @param string $currency
 *
 * @return self
 */
public function addProduct($product, int $quantity = 1, string $currency = "USD") {}

/**
 * Setter for order
 *
 * @param mixed $order
 * @param string $locationId
 * @param string $currency
 *
 * @return self
 */
public function setOrder($order, string $locationId, string $currency = "USD") {}

/**
 * Getter for customer.
 * 
 * @return mixed
 */
public function getCustomer() {}

/**
 * Setter for customer.
 * 
 * @param mixed $customer 
 * @return self
 */
public function setCustomer($customer) {}

/**
 * Getter for customer.
 * 
 * @return mixed
 */
public function getMerchant() {}

/**
 * Setter for merchant.
 * 
 * @param mixed $merchant 

 * @return self
 */
public function setMerchant($merchant) {}
```

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
