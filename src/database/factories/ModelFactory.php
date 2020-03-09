<?php

use Illuminate\Database\Eloquent\Factory as EloquentFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Utils\Constants;

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
        'name'       => $faker->unique()->company,
        'type'       => Arr::random([Constants::TAX_ADDITIVE, Constants::TAX_INCLUSIVE]),
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
        'name'       => $faker->unique()->company,
        'percentage' => $faker->randomFloat(2, 1, 100),
        'amount'     => $faker->numberBetween(1, 100),
    ];
});
/* PERCENTAGE ONLY */
$factory->state(Constants::DISCOUNT_NAMESPACE, 'PERCENTAGE_ONLY', function (Faker\Generator $faker) {
    return [
        'percentage' => $faker->randomFloat(2, 1, 100),
        'amount'     => null,
    ];
});
/* AMOUNT ONLY */
$factory->state(Constants::DISCOUNT_NAMESPACE, 'AMOUNT_ONLY', function (Faker\Generator $faker) {
    return [
        'percentage' => null,
        'amount'     => $faker->numberBetween(1, 100),
    ];
});
/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::PRODUCT_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'name'           => $faker->unique()->firstName,
        'price'          => $faker->unique()->numberBetween(5000, 50000),
        'variation_name' => $faker->realText(10),
        'note'           => $faker->realText(50),
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::CUSTOMER_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'first_name'   => $faker->unique()->firstNameMale,
        'last_name'    => $faker->unique()->lastName,
        'company_name' => $faker->unique()->address,
        'nickname'     => $faker->unique()->firstNameFemale,
        'email'        => $faker->unique()->companyEmail,
        'phone'        => $faker->unique()->tollFreePhoneNumber,
        'note'         => $faker->unique()->paragraph(5),
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::TRANSACTION_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'status'   => Arr::random([Constants::TRANSACTION_STATUS_OPENED, Constants::TRANSACTION_STATUS_PASSED, Constants::TRANSACTION_STATUS_FAILED]),
        'amount'   => $faker->numberBetween(5000, 500000),
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
$factory->define(Order::class, function (Faker\Generator $faker) {
    return [
        'payment_service_type' => 'square',
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(User::class, function (Faker\Generator $faker) {
    static $password;

    return [
        'name'           => $faker->name,
        'email'          => $faker->unique()->safeEmail,
        'password'       => $password ?: $password = bcrypt('secret'),
        'remember_token' => Str::random(10),
    ];
});
