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
$factory->define(Order::class, function (Faker\Generator $faker) {
    return [
        'payment_service_type' => 'square',
        'location_id' => env('SQUARE_LOCATION'),
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

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::WEBHOOK_SUBSCRIPTION_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'square_id' => 'wh_' . $faker->unique()->uuid,
        'name' => $faker->words(3, true) . ' Webhook',
        'notification_url' => 'https://' . $faker->domainName . '/webhook/' . $faker->uuid,
        'event_types' => Arr::random([
            ['order.created'],
            ['order.updated'],
            ['order.fulfillment.updated'],
            ['order.created', 'order.updated'],
            ['order.created', 'order.updated', 'order.fulfillment.updated'],
        ]),
        'api_version' => '2024-06-04',
        'signature_key' => 'wh_key_' . $faker->sha256,
        'is_enabled' => $faker->boolean(80), // 80% chance of being enabled
        'is_active' => $faker->boolean(90), // 90% chance of being active
    ];
});

/* ENABLED WEBHOOK */
$factory->state(Constants::WEBHOOK_SUBSCRIPTION_NAMESPACE, 'ENABLED', [
    'is_enabled' => true,
    'is_active' => true,
]);

/* DISABLED WEBHOOK */
$factory->state(Constants::WEBHOOK_SUBSCRIPTION_NAMESPACE, 'DISABLED', [
    'is_enabled' => false,
]);

/* INACTIVE WEBHOOK */
$factory->state(Constants::WEBHOOK_SUBSCRIPTION_NAMESPACE, 'INACTIVE', [
    'is_active' => false,
]);

/* ORDER EVENTS WEBHOOK */
$factory->state(Constants::WEBHOOK_SUBSCRIPTION_NAMESPACE, 'ORDER_EVENTS', [
    'event_types' => ['order.created', 'order.updated', 'order.fulfillment.updated'],
]);

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::WEBHOOK_EVENT_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'square_event_id' => 'event_' . $faker->unique()->uuid,
        'event_type' => Arr::random([
            'order.created',
            'order.updated',
            'order.fulfillment.updated',
            'payment.created',
            'payment.updated',
        ]),
        'event_data' => [
            'merchant_id' => 'merchant_' . $faker->uuid,
            'type' => 'order.created',
            'event_id' => 'event_' . $faker->uuid,
            'created_at' => $faker->iso8601,
            'data' => [
                'type' => 'order',
                'id' => 'order_data_' . $faker->uuid,
                'object' => [
                    'order' => [
                        'id' => 'order_' . $faker->uuid,
                        'location_id' => 'location_' . $faker->uuid,
                        'state' => Arr::random(['DRAFT', 'OPEN', 'COMPLETED', 'CANCELED']),
                    ]
                ]
            ]
        ],
        'event_time' => $faker->dateTimeBetween('-1 month', 'now'),
        'status' => Arr::random(['pending', 'processed', 'failed']),
        'subscription_id' => function () {
            return factory(Constants::WEBHOOK_SUBSCRIPTION_NAMESPACE)->create()->id;
        },
    ];
});

/* PENDING WEBHOOK EVENT */
$factory->state(Constants::WEBHOOK_EVENT_NAMESPACE, 'PENDING', [
    'status' => 'pending',
    'processed_at' => null,
    'error_message' => null,
]);

/* PROCESSED WEBHOOK EVENT */
$factory->state(Constants::WEBHOOK_EVENT_NAMESPACE, 'PROCESSED', function (Faker\Generator $faker) {
    return [
        'status' => 'processed',
        'processed_at' => $faker->dateTimeBetween('-1 week', 'now'),
        'error_message' => null,
    ];
});

/* FAILED WEBHOOK EVENT */
$factory->state(Constants::WEBHOOK_EVENT_NAMESPACE, 'FAILED', function (Faker\Generator $faker) {
    return [
        'status' => 'failed',
        'processed_at' => $faker->dateTimeBetween('-1 week', 'now'),
        'error_message' => $faker->sentence,
    ];
});

/* ORDER CREATED EVENT */
$factory->state(Constants::WEBHOOK_EVENT_NAMESPACE, 'ORDER_CREATED_EVENT', [
    'event_type' => 'order.created',
    'event_data' => [
        'merchant_id' => 'merchant-123',
        'type' => 'order.created',
        'event_id' => 'event-123',
        'created_at' => now()->toIso8601String(),
        'data' => [
            'type' => 'order_created',
            'id' => 'order-data-123',
            'object' => [
                'order_created' => [
                    'created_at' => now()->toIso8601String(),
                    'location_id' => 'location-789',
                    'order_id' => 'order-456',
                    'state' => 'OPEN',
                    'version' => 1,
                ]
            ]
        ]
    ],
    'status' => 'pending',
]);

/* PAYMENT EVENT */
$factory->state(Constants::WEBHOOK_EVENT_NAMESPACE, 'PAYMENT_CREATED_EVENT', function (Faker\Generator $faker) {
    return [
        'event_type' => 'payment.created',
        'event_data' => [
            'merchant_id' => 'merchant_' . $faker->uuid,
            'type' => 'payment.created',
            'event_id' => 'event_' . $faker->uuid,
            'created_at' => $faker->iso8601,
            'data' => [
                'type' => 'payment',
                'id' => 'payment_data_' . $faker->uuid,
                'object' => [
                    'payment' => [
                        'id' => 'payment_' . $faker->uuid,
                        'location_id' => 'location_' . $faker->uuid,
                        'status' => Arr::random(['PENDING', 'COMPLETED', 'CANCELED', 'FAILED']),
                    ]
                ]
            ]
        ],
    ];
});
