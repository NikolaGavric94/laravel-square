nikolag/square [![Latest Unstable Version](https://poser.pugx.org/nikolag/square/v/unstable)](https://packagist.org/packages/nikolag/square) [![Latest Stable Version](https://poser.pugx.org/nikolag/square/v/stable)](https://packagist.org/packages/nikolag/square) [![Total Downloads](https://poser.pugx.org/nikolag/square/downloads)](https://packagist.org/packages/nikolag/square) [![License](https://poser.pugx.org/nikolag/square/license)](https://packagist.org/packages/nikolag/square) [![Build Status](https://travis-ci.org/NikolaGavric94/laravel-square.svg?branch=develop)](https://travis-ci.org/NikolaGavric94/laravel-square)
=========
Square integration with laravel 5.4.x

## Installation guide
`composer require nikolag/square --dev`

Open `app.php` file and add:
```javascript
//providers
Nikolag\Square\Providers\SquareServiceProvider::class

//aliases
'Square' => Nikolag\Square\Facades\Square::class
```

Publish the configuration file needed for library to work with the following command:
```javascript
php artisan vendor:publish --tag=nikolag_config
```

After that also add your credentials for Square API inside of `.env` and also add fully qualified name for your classes.
```javascript
SQUARE_APPLICATION_ID=<YOUR_APPLICATION_ID>
SQUARE_TOKEN=<YOUR_ACCESS_TOKEN>

SQUARE_USER_NAMESPACE=<USER_NAMESPACE>
SQUARE_ORDER_NAMESPACE=<ORDER_NAMESPACE>
```

To be able to utilize the customers system for Users, your User class must use HasCustomers trait.
```javascript
<?php
...
use Nikolag\Square\Traits\HasCustomers;

class User extends Model {
  use HasCustomers;
  ...
}
```

## Examples
#### Simple usages
```javascript
public function charge() {
  //$amount is in USD currency and is in cents. ($amount = 200 == 2 Dollars)
  $amount = 5000;
  //nonce reference => https://docs.connect.squareup.com/articles/adding-payment-form
  $formNonce = 'some nonce';
  //$location_id is id of a location from Square
  $location_id = 'some location id';
  Square::charge($amount, $formNonce, $location_id);

  $customer = array(
    'first_name' => $request->first_name,
    'last_name' => $request->last_name,
    'company_name' => $request->company_name,
    'nickname' => $request->nickname,
    'email' => $request->email,
    'phone' => $request->phone,
    'note' => $request->note,
  );
  //or
  $customer = $merchant->hasCustomer($request->email);

  Square::setMerchant($merchant)->setCustomer($customer)->charge($amount, $formNonce, $location_id);
}
```

#### Retrieve all customers for a merchant
```javascript
$merchant->customers;
```

#### Retrieve a customer by email
```javascript
$merchant->hasCustomer('tester@gmail.com');
```

#### Retrieve all transactions for a merchant
```javascript
$merchant->transactions;
```

#### Retrieve all transactions by status
```javascript
//Transactions that passed
$merchant->passedTransactions();
//Transactions that failed
$merchant->failedTransactions();
//Transactions that are pending
$merchant->openedTransactions();
```

#### Charge customers with merchant as a seller
Charging a customer that doesn't exist and connecting it with a merchant and a transaction.
```javascript
public function chargeCustomerAsArray(Request $request) {
  //$amount is in USD currency and is in cents. ($amount = 200 == 2 Dollars)
  $amount = 5000;
  //nonce reference => https://docs.connect.squareup.com/articles/adding-payment-form
  $formNonce = 'some nonce';
  //$location_id is id of a location from Square
  $location_id = 'some location id';
  $customer = array(
      'first_name' => $request->first_name,
      'last_name' => $request->last_name,
      'company_name' => $request->company_name,
      'nickname' => $request->nickname,
      'email' => $request->email,
      'phone' => $request->phone,
      'note' => $request->note
  );

  $merchant->charge($amount, $formNonce, $location_id, $customer);
}
```
Charging already existing customer and connecting both transaction and merchant with it
```javascript
public function chargeCustomerAsArray(Request $request) {
  //$amount is in USD currency and is in cents. ($amount = 200 == 2 Dollars)
  $amount = 5000;
  //nonce reference => https://docs.connect.squareup.com/articles/adding-payment-form
  $formNonce = 'some nonce';
  //$location_id is id of a location from Square
  $location_id = 'some location id';
  $customer = $merchant->hasCustomer($request->email);
  if(!$customer) $customer = null;

  $merchant->charge($amount, $formNonce, $location_id, $customer);
}
```
Charging a customer without saving the customer, but connecting the transaction with the merchant.
```javascript
public function chargeCustomerAsArray(Request $request) {
  //$amount is in USD currency and is in cents. ($amount = 200 == 2 Dollars)
  $amount = 5000;
  //nonce reference => https://docs.connect.squareup.com/articles/adding-payment-form
  $formNonce = 'some nonce';
  //$location_id is id of a location from Square
  $location_id = 'some location id';

  $merchant->charge($amount, $formNonce, $location_id);
}
```

## All available methods
### Trait
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
 * @return \Nikolag\Square\Model\Customer|false
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
 * @param \Illuminate\Database\Eloquent\Builder $query 
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopePassedTransactions($query) {}

/**
 * Pending transactions.
 * 
 * @param \Illuminate\Database\Eloquent\Builder $query 
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeOpenedTransactions($query) {}

/**
 * Failed transactions.
 * 
 * @param \Illuminate\Database\Eloquent\Builder $query 
 * @return \Illuminate\Database\Eloquent\Builder
 */
public function scopeFailedTransactions($query) {}

/**
 * Charge a customer.
 * 
 * @param float $amount 
 * @param string $nonce 
 * @param string $location_id 
 * @param \Nikolag\Square\Models\Customer|array $customer
 * @return \Nikolag\Square\Models\Transaction
 */
public function charge(float $amount, string $nonce, string $location_id, $customer = null) {}

/**
 * Save a customer.
 * 
 * @param array $customer 
 * @return void
 */
public function saveCustomer(array $customer) {}
```
### Facade
```javascript
/**
 * Charge a customer.
 * 
 * @param float $amount 
 * @param string $card_nonce 
 * @param string $location_id 
 * @return \Nikolag\Square\Models\Transaction
 * @throws \Nikolag\Square\Exception on non-2xx response
 */
public function charge(float $amount, string $card_nonce, string $location_id) {}

/**
 * @param \Nikolag\Square\Models\Customer|null $customer
 *
 * @return self
 */
public function setCustomer($customer) {}

/**
 * @param any $merchant
 *
 * @return self
 */
public function setMerchant($merchant) {}
```

## Contributing
Everyone is welcome to contribute to this repository, simply open up an issue
and label the request, whether it is an issue, bug or a feature. For any other
enquiries send an email to nikola.gavric94@gmail.com

## License
MIT License

Copyright (c) 2017

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