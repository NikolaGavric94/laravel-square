<?php

use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Transaction;
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

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::CUSTOMER_NAMESPACE, function (Faker\Generator $faker)
{
    return [
    	'square_id' => $faker->unique()->randomNumber,
        'first_name' => $faker->unique()->firstNameMale,
        'last_name' => $faker->unique()->lastName,
        'company_name' => $faker->unique()->address,
        'nickname' => $faker->unique()->firstNameFemale,
        'email' => $faker->unique()->companyEmail,
        'phone' => $faker->unique()->tollFreePhoneNumber,
        'note' => $faker->unique()->paragraph(5),
    ];
});

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::TRANSACTION_NAMESPACE, function (Faker\Generator $faker)
{
    return [
        'status' => Constants::TRANSACTION_STATUS_OPENED,
        'amount' => $faker->numberBetween(5000, 500000)
    ];
});

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(User::class, function (Faker\Generator $faker)
{
    static $password;

    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => str_random(10),
    ];
});
