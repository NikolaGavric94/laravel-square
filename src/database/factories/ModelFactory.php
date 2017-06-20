<?php

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
$factory->define(Nikolag\Square\Customer::class, function (Faker\Generator $faker) {

    return [
    	'square_id' => $faker->unique()->randomNumber,
        'first_name' => $faker->unique()->firstNameMale,
        'last_name' => $faker->unique()->lastName,
        'company_name' => $faker->unique()->address,
        'nickname' => $faker->unique()->firstNameFemale,
        'email' => $faker->unique()->companyEmail,
        'phone' => $faker->unique()->tollFreePhoneNumber,
        'note' => $faker->unique()->paragraph(5)
    ];
});