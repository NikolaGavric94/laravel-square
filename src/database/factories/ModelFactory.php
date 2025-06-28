<?php

use Illuminate\Database\Eloquent\Factory as EloquentFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Models\ModifierOption;
use Nikolag\Square\Models\OrderProductModifierPivot;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;
use Square\Models\CatalogModifierListSelectionType;
use Square\Models\FulfillmentState;
use Square\Models\FulfillmentType;

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
        'price_money_amount' => $faker->numberBetween(5_00, 10_00),
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
$factory->define(Modifier::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->word,
        'selection_type' => CatalogModifierListSelectionType::SINGLE,
        'square_catalog_object_id' => $faker->unique()->uuid,
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(ModifierOption::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->word,
        'price_money_amount' => $faker->numberBetween(100, 1000),
        'price_money_currency' => 'USD',
        'modifier_id' => function () {
            return factory(Modifier::class)->create()->id;
        },
        'square_catalog_object_id' => $faker->unique()->uuid,
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(OrderProductModifierPivot::class, function (Faker\Generator $faker, array $data) {
    if (!isset($data['modifiable_id']) || !isset($data['modifiable_type'])) {
        $modifier = factory(ModifierOption::class)->create();
    }
    return [
        'modifiable_id' => $modifier->id,
        'modifiable_type' => get_class($modifier),
        // Always assume the tests will associate the following fields prior to saving, generating orders and products
        // on the fly will likely cause issues that will be uncommon in production scenarios:
        // 'product_order_id'
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(DeliveryDetails::class, function (Faker\Generator $faker) {
    return [
        'schedule_type' => Constants::SCHEDULE_TYPE_ASAP,
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
$factory->define(Constants::SERVICE_CHARGE_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'name' => $faker->unique()->company,
        'taxable' => true,
    ];
});

/* PERCENTAGE ONLY */
$factory->state(Constants::SERVICE_CHARGE_NAMESPACE, 'PERCENTAGE_ONLY', function (Faker\Generator $faker) {
    return [
        'percentage' => $faker->randomFloat(2, 1, 25),
        'amount_money' => null,
    ];
});

/* AMOUNT ONLY */
$factory->state(Constants::SERVICE_CHARGE_NAMESPACE, 'AMOUNT_ONLY', function (Faker\Generator $faker) {
    return [
        'amount_money' => $faker->numberBetween(100, 1000),
        'amount_currency' => 'USD',
        'percentage' => null,
    ];
});

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Fulfillment::class, function (Faker\Generator $faker) {
    return [
        'state' => FulfillmentState::PROPOSED,
        'uid' => Util::uid(),
    ];
});

$factory->afterCreating(Fulfillment::class, function ($fulfillment, $faker) {
    // Determine the state of the factory
    if ($fulfillment->type === FulfillmentType::DELIVERY) {
        $fulfillment->fulfillmentDetails()->associate(factory(DeliveryDetails::class)->create());
    } elseif ($fulfillment->type === FulfillmentType::PICKUP) {
        $fulfillment->fulfillmentDetails()->associate(factory(PickupDetails::class)->create());
    } elseif ($fulfillment->type === FulfillmentType::SHIPMENT) {
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
    if ($fulfillment->type === FulfillmentType::DELIVERY) {
        $fulfillmentDetails = factory(DeliveryDetails::class)->make();
    } elseif ($fulfillment->type === FulfillmentType::PICKUP) {
        $fulfillmentDetails = factory(PickupDetails::class)->make();
    } elseif ($fulfillment->type === FulfillmentType::SHIPMENT) {
        $fulfillmentDetails = factory(ShipmentDetails::class)->make();
    }

    // Associate the fulfillmentDetails with the fulfillment
    $fulfillment->fulfillmentDetails()->associate($fulfillmentDetails);
});

/* DELIVERY fulfillment state */
$factory->state(Fulfillment::class, FulfillmentType::DELIVERY, function () {
    return [
        'type' => FulfillmentType::DELIVERY,
    ];
});

/* PICKUP fulfillment state */
$factory->state(Fulfillment::class, FulfillmentType::PICKUP, function () {
    return [
        'type' => FulfillmentType::PICKUP,
    ];
});

/* SHIPMENT fulfillment state */
$factory->state(Fulfillment::class, FulfillmentType::SHIPMENT, function () {
    return [
        'type' => FulfillmentType::SHIPMENT,
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
        'schedule_type' => Constants::SCHEDULE_TYPE_ASAP,
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
        'address' => [
            'address_line_1' => $faker->streetAddress,
            'address_line_2' => $faker->secondaryAddress,
            'locality' => $faker->city,
            'administrative_district_level_1' => $faker->state,
            'postal_code' => $faker->postcode,
            'country' => $faker->country,
        ],
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

/* @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(Constants::ORDER_RETURN_NAMESPACE, function (Faker\Generator $faker) {
    return [
        'uid' => $faker->unique()->uuid,
        'source_order_id' => function () use($faker) {
            // Create a new order and spoof an id
            $order = factory(config('nikolag.connections.square.order.namespace'))->create();
            $property = config('nikolag.connections.square.order.service_identifier');
            $order->{$property} = $faker->unique()->uuid;

            $order->save();
            return $order->{$property};
        },
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
        'webhook_subscription_id' => function () {
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
            'created_at' =>  now()->toIso8601String(),
            'data' => [
                'type' => 'payment',
                'id' => 'payment_data_id_' . $faker->uuid,
                'object' => [
                    'payment' => [
                        'id' => 'payment_id_444',
                        'created_at' => '2020-11-22T21:16:51.086Z',
                        'updated_at' => '2020-11-22T21:16:51.198Z',
                        'amount_money' => [
                            'amount' => 100,
                            'currency' => 'USD'
                        ],
                        'status' => 'APPROVED',
                        'delay_duration' => 'PT168H',
                        'source_type' => 'CARD',
                        'card_details' => [
                            'status' => 'AUTHORIZED',
                            'card' => [
                                'card_brand' => 'MASTERCARD',
                                'last_4' => '9029',
                                'exp_month' => 11,
                                'exp_year' => 2022,
                                'fingerprint' => 'sq-1-Tvruf3vPQxlvI6n0IcKYfBukrcv6IqWr8UyBdViWXU2yzGn5VMJvrsHMKpINMhPmVg',
                                'card_type' => 'CREDIT',
                                'prepaid_type' => 'NOT_PREPAID',
                                'bin' => '540988'
                            ],
                            'entry_method' => 'KEYED',
                            'cvv_status' => 'CVV_ACCEPTED',
                            'avs_status' => 'AVS_ACCEPTED',
                            'statement_description' => 'SQ *DEFAULT TEST ACCOUNT',
                            'card_payment_timeline' => [
                                'authorized_at' => '2020-11-22T21:16:51.198Z'
                            ]
                        ],
                        'location_id' => 'location-242',
                        'order_id' => '03O3USaPaAaFnI6kkwB1JxGgBsUZY',
                        'risk_evaluation' => [
                            'created_at' => '2020-11-22T21:16:51.198Z',
                            'risk_level' => 'NORMAL'
                        ],
                        'total_money' => [
                            'amount' => 100,
                            'currency' => 'USD'
                        ],
                        'approved_money' => [
                            'amount' => 100,
                            'currency' => 'USD'
                        ],
                        'capabilities' => [
                            'EDIT_TIP_AMOUNT',
                            'EDIT_TIP_AMOUNT_UP',
                            'EDIT_TIP_AMOUNT_DOWN'
                        ],
                        'receipt_number' => 'hYy9',
                        'delay_action' => 'CANCEL',
                        'delayed_until' => '2020-11-29T21:16:51.086Z',
                        'version_token' => 'FfQhQJf9r3VSQIgyWBk1oqhIwiznLwVwJbVVA0bdyEv6o'
                    ]
                ]
            ]
        ]
    ];
});
