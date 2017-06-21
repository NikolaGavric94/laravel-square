[![Latest Unstable Version](https://poser.pugx.org/nikolag/square/v/unstable)](https://packagist.org/packages/nikolag/square)
[![Latest Stable Version](https://poser.pugx.org/nikolag/square/v/stable)](https://packagist.org/packages/nikolag/square)
[![Total Downloads](https://poser.pugx.org/nikolag/square/downloads)](https://packagist.org/packages/nikolag/square)
[![License](https://poser.pugx.org/nikolag/square/license)](https://packagist.org/packages/nikolag/square)
[![Build Status](https://travis-ci.org/NikolaGavric94/laravel-square.svg?branch=develop)](https://travis-ci.org/NikolaGavric94/laravel-square)
# laravel-square
Square integration with laravel 5.

## Installation guide
`composer require "nikolag/square":"dev-master" --dev`

Open `app.php` file and add:
```javascript
//providers
Nikolag\Square\Providers\SquareServiceProvider::class

//aliases
'Square' => Nikolag\Square\Facades\Square::class
```

Open `services.php` file and add the following code at the bottom of the file:
```javascript
'square' => [
    'application_id' => env('SQUARE_APPLICATION_ID'),
    'access_token' => env('SQUARE_TOKEN'),
    'location_id' => env('SQUARE_LOCATION')
]
```

After that also add your credentials for Square API inside of `.env`:
```javascript
SQUARE_APPLICATION_ID=<YOUR_APPLICATION_ID>
SQUARE_TOKEN=<YOUR_ACCESS_TOKEN>
SQUARE_LOCATION=<YOUR_LOCATION_ID>
```

## Examples

Charging a customer.

```javascript
$data = array(
  'firstName' => 'John',
  'lastName' => 'Doe',
  'companyName' => 'John Doe LTD.',
  'nickname' => 'Johny',
  'email' => 'john.doe@example.com',
  'phone' => '+123325990',
  //An optional ID you can associate with the transaction for your own purposes
  //such as to associate the transaction with an entity ID in your own database.
  'reference_id' => '555333',
  'note' => 'This is a trusted customer.'
);
//$data as a parameter when constructing SquareCustomer
//is optional.
$customer = new SquareCustomer($data);
//or
$customer = new SquareCustomer();
//$amount is in USD currency and is in cents. ($amount = 200 == 2 Dollars)
//nonce reference => https://docs.connect.squareup.com/articles/adding-payment-form
$transaction = $customer->charge($amount, $nonce);
```

## Still in development, a lot more features yet to be built...
