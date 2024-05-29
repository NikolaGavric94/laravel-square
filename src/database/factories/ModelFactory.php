<?php

use Illuminate\Database\Eloquent\Factory as EloquentFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

$factory = app(EloquentFactory::class);

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::TAX_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'name' => $faker->unique()->company,
        'type' => Arr::random([Constants::TAX_ADDITIVE, Constants::TAX_INCLUSIVE]),
        'percentage' => $faker->randomFloat(2, 1, 100),
    ];
});
/* ADDITIVE TAX */
$factory->state(Constants::TAX_NAMESPACE, 'ADDITIVE', [
    'type' => Constants::TAX_ADDITIVE,
]);
/* INCLUSIVE TAX */
$factory->state(Constants::TAX_NAMESPACE, 'INCLUSIVE', [
    'type' => Constants::TAX_INCLUSIVE,
]);

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::ORDER_PRODUCT_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'quantity' => 1,
        'order_id' => function () {
            return factory(Order::class)->create();
        },
        'product_id' => function () {
            return factory(Constants::PRODUCT_NAMESPACE)->create();
        },
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::DISCOUNT_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'name' => $faker->unique()->company,
    ];
});
/* PERCENTAGE ONLY */
$factory->state(Constants::DISCOUNT_NAMESPACE, 'PERCENTAGE_ONLY', function (Faker\Generator $faker) {
    return [
        'percentage' => $faker->randomFloat(2, 1, 100),
    ];
});
/* AMOUNT ONLY */
$factory->state(Constants::DISCOUNT_NAMESPACE, 'AMOUNT_ONLY', function (Faker\Generator $faker) {
    return [
        'amount' => $faker->numberBetween(1, 100),
    ];
});
/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::PRODUCT_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'name' => $faker->unique()->firstName,
        'price' => $faker->unique()->numberBetween(5000, 50000),
        'variation_name' => $faker->realText(10),
        'note' => $faker->realText(50),
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::CUSTOMER_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'first_name' => $faker->unique()->firstNameMale,
        'last_name' => $faker->unique()->lastName,
        'company_name' => $faker->unique()->address,
        'nickname' => $faker->unique()->firstNameFemale,
        'email' => $faker->unique()->companyEmail,
        'phone' => $faker->unique()->tollFreePhoneNumber,
        'note' => $faker->unique()->paragraph(5),
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::TRANSACTION_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'status' => Arr::random([Constants::TRANSACTION_STATUS_OPENED, Constants::TRANSACTION_STATUS_PASSED, Constants::TRANSACTION_STATUS_FAILED]),
        'amount' => $faker->numberBetween(5000, 500000),
        'currency' => 'USD',
    ];
});
/* PENDING TRANSACTION */
$factory->state(Constants::TRANSACTION_NAMESPACE, 'OPENED', [
    'status' => Constants::TRANSACTION_STATUS_OPENED,
]);
/* PAID TRANSACTION */
$factory->state(Constants::TRANSACTION_NAMESPACE, 'PASSED', [
    'status' => Constants::TRANSACTION_STATUS_PASSED,
]);
/* FAILED TRANSACTION */
$factory->state(Constants::TRANSACTION_NAMESPACE, 'FAILED', [
    'status' => Constants::TRANSACTION_STATUS_FAILED,
]);

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(DeliveryDetails::class, function (Faker\Generator $faker) {
    return [
        'carrier' => $faker->company,
        'placed_at' => now(),
        'deliver_at' => $faker->dateTimeBetween('now', '+1 month'),
        'note' => $faker->realText(50),
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::DISCOUNT_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'name' => $faker->unique()->company,
    ];
});


/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Fulfillment::class, function (Faker\Generator $faker) {
    return [
        'state' => Constants::FULFILLMENT_STATE_PROPOSED,
        'uid'   => Util::uid(),
    ];
});

$factory->afterCreating(Fulfillment::class, function ($fulfillment, $faker) {
    // Determine the state of the factory
    if ($fulfillment->type === Constants::FULFILLMENT_TYPE_DELIVERY) {
        $fulfillment->fulfillmentDetails()->associate(factory(DeliveryDetails::class)->create());
    } elseif ($fulfillment->type === Constants::FULFILLMENT_TYPE_PICKUP) {
        $fulfillment->fulfillmentDetails()->associate(factory(PickupDetails::class)->create());
    } elseif ($fulfillment->type === Constants::FULFILLMENT_TYPE_SHIPMENT) {
        $fulfillment->fulfillmentDetails()->associate(factory(ShipmentDetails::class)->create());
    }

    // Add a recipient
    $fulfillment->recipient()->associate(factory(Recipient::class)->create());
});

$factory->afterMaking(Fulfillment::class, function ($fulfillment, $faker) {
    // Make a recipient we can attach
    $recipient = factory(Recipient::class)->make();
    // Determine the state of the factory
    $fulfillmentDetails = null;
    if ($fulfillment->type === Constants::FULFILLMENT_TYPE_DELIVERY) {
        $fulfillmentDetails = factory(DeliveryDetails::class)->make();
    } elseif ($fulfillment->type === Constants::FULFILLMENT_TYPE_PICKUP) {
        $fulfillmentDetails = factory(PickupDetails::class)->make();
    } elseif ($fulfillment->type === Constants::FULFILLMENT_TYPE_SHIPMENT) {
        $fulfillmentDetails = factory(ShipmentDetails::class)->make();
    }

    // Associate the fulfillmentDetails with the fulfillment
    $fulfillment->fulfillmentDetails()->associate($fulfillmentDetails);
});

/* DELIVERY fulfillment state */
$factory->state(Fulfillment::class, Constants::FULFILLMENT_TYPE_DELIVERY, function () {
    return [
        'type' => Constants::FULFILLMENT_TYPE_DELIVERY,
    ];
});

/* PICKUP fulfillment state */
$factory->state(Fulfillment::class, Constants::FULFILLMENT_TYPE_PICKUP, function () {
    return [
        'type' => Constants::FULFILLMENT_TYPE_PICKUP,
    ];
});

/* SHIPMENT fulfillment state */
$factory->state(Fulfillment::class, Constants::FULFILLMENT_TYPE_SHIPMENT, function () {
    return [
        'type' => Constants::FULFILLMENT_TYPE_SHIPMENT,
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Order::class, function (Faker\Generator $faker) {
    return [
        'payment_service_type' => 'square',
        'location_id'          => env('SQUARE_LOCATION'),
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(PickupDetails::class, function (Faker\Generator $faker) {
    return [
        'expires_at' => $faker->dateTimeBetween('now', '+1 day'),
        'scheduled_type' => Constants::SCHEDULED_TYPE_ASAP,
        'pickup_at' => now(),
        'note' => $faker->realText(50),
        'placed_at' => now(),
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Recipient::class, function (Faker\Generator $faker) {
    return [
        'display_name' => $faker->name,
        'email_address' => $faker->unique()->safeEmail,
        'phone_number' => $faker->unique()->tollFreePhoneNumber,
        'address' => $faker->address,
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(ShipmentDetails::class, function (Faker\Generator $faker) {
    return [
        'carrier' => $faker->company,
        'placed_at' => now(),
        'shipping_note' => $faker->realText(50),
        'shipping_type' => Arr::random(['First Class', 'Priority', 'Express']),
        'tracking_number' => $faker->unique()->randomNumber(9),
        'tracking_url' => $faker->url,
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(User::class, function (Faker\Generator $faker) {
    static $password;

    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => Str::random(10),
    ];
});
