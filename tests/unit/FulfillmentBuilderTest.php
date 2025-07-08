<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Builders\FulfillmentBuilder;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Models\ShipmentDetails;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Square\Models\FulfillmentType;

class FulfillmentBuilderTest extends TestCase
{
    private TestDataHolder $data;
    private FulfillmentBuilder $builder;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->data = TestDataHolder::create();
        $this->builder = new FulfillmentBuilder();
    }

    /**
     * Test creating fulfillment from model with delivery details.
     *
     * @return void
     */
    public function test_create_fulfillment_from_model_with_delivery(): void
    {
        // Create a delivery fulfillment with associated details
        $deliveryDetails = factory(DeliveryDetails::class)->create();
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make([
            'type' => FulfillmentType::DELIVERY,
        ]);
        $fulfillment->fulfillmentDetails()->associate($deliveryDetails);

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $fulfillment->recipient = $recipient;

        $result = $this->builder->createFulfillmentFromModel($fulfillment, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::DELIVERY, $result->type);
        $this->assertInstanceOf(DeliveryDetails::class, $result->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $result->recipient);
    }

    /**
     * Test creating fulfillment from model with pickup details.
     *
     * @return void
     */
    public function test_create_fulfillment_from_model_with_pickup(): void
    {
        // Create a pickup fulfillment with associated details
        $pickupDetails = factory(PickupDetails::class)->create();
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::PICKUP)->make([
            'type' => FulfillmentType::PICKUP,
        ]);
        $fulfillment->fulfillmentDetails()->associate($pickupDetails);

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $fulfillment->recipient = $recipient;

        $result = $this->builder->createFulfillmentFromModel($fulfillment, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::PICKUP, $result->type);
        $this->assertInstanceOf(PickupDetails::class, $result->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $result->recipient);
    }

    /**
     * Test creating fulfillment from model with shipment details.
     *
     * @return void
     */
    public function test_create_fulfillment_from_model_with_shipment(): void
    {
        // Create a shipment fulfillment with associated details
        $shipmentDetails = factory(ShipmentDetails::class)->create();
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::SHIPMENT)->make([
            'type' => FulfillmentType::SHIPMENT,
        ]);
        $fulfillment->fulfillmentDetails()->associate($shipmentDetails);

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $fulfillment->recipient = $recipient;

        $result = $this->builder->createFulfillmentFromModel($fulfillment, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::SHIPMENT, $result->type);
        $this->assertInstanceOf(ShipmentDetails::class, $result->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $result->recipient);
    }

    /**
     * Test creating fulfillment from model with mismatched type and details.
     *
     * @return void
     */
    public function test_create_fulfillment_from_model_with_mismatched_type(): void
    {
        // Create a delivery fulfillment but with pickup details (intentional mismatch)
        $pickupDetails = factory(PickupDetails::class)->create();
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make([
            'type' => FulfillmentType::DELIVERY,
        ]);
        $fulfillment->fulfillmentDetails()->associate($pickupDetails);

        $this->expectException(InvalidSquareOrderException::class);
        $this->expectExceptionMessage('Fulfillment type does not match details');

        $this->builder->createFulfillmentFromModel($fulfillment, $this->data->order);
    }

    /**
     * Test creating fulfillment from array with delivery details.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_with_delivery(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::DELIVERY,
            'delivery_details' => [
                'schedule_type' => 'ASAP',
                'placed_at' => now()->toDateTimeString(),
                'deliver_at' => now()->addHour()->toDateTimeString(),
                'note' => 'Test delivery note',
                'recipient' => [
                    'display_name' => 'John Doe',
                    'email_address' => 'john@example.com',
                    'phone_number' => '+1234567890',
                    'address' => [
                        'address_line_1' => '123 Main St',
                        'locality' => 'Anytown',
                        'administrative_district_level_1' => 'CA',
                        'postal_code' => '12345',
                        'country' => 'US',
                    ],
                ],
            ],
        ];

        $result = $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::DELIVERY, $result->type);
        $this->assertInstanceOf(DeliveryDetails::class, $result->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $result->recipient);
        $this->assertEquals('John Doe', $result->recipient->display_name);
        $this->assertEquals('john@example.com', $result->recipient->email_address);
    }

    /**
     * Test creating fulfillment from array with pickup details.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_with_pickup(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::PICKUP,
            'pickup_details' => [
                'expires_at' => now()->addDay()->toDateTimeString(),
                'schedule_type' => 'ASAP',
                'pickup_at' => now()->toDateTimeString(),
                'note' => 'Test pickup note',
                'placed_at' => now()->toDateTimeString(),
                'recipient' => [
                    'display_name' => 'Jane Doe',
                    'email_address' => 'jane@example.com',
                    'phone_number' => '+1234567891',
                    'address' => [
                        'address_line_1' => '456 Oak Ave',
                        'locality' => 'Somewhere',
                        'administrative_district_level_1' => 'NY',
                        'postal_code' => '67890',
                        'country' => 'US',
                    ],
                ],
            ],
        ];

        $result = $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::PICKUP, $result->type);
        $this->assertInstanceOf(PickupDetails::class, $result->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $result->recipient);
        $this->assertEquals('Jane Doe', $result->recipient->display_name);
        $this->assertEquals('jane@example.com', $result->recipient->email_address);
    }

    /**
     * Test creating fulfillment from array with shipment details.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_with_shipment(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::SHIPMENT,
            'shipment_details' => [
                'carrier' => 'FedEx',
                'placed_at' => now()->toDateTimeString(),
                'shipping_note' => 'Test shipment note',
                'shipping_type' => 'Express',
                'tracking_number' => '123456789',
                'tracking_url' => 'https://tracking.example.com/123456789',
                'recipient' => [
                    'display_name' => 'Bob Smith',
                    'email_address' => 'bob@example.com',
                    'phone_number' => '+1234567892',
                    'address' => [
                        'address_line_1' => '789 Pine St',
                        'locality' => 'Elsewhere',
                        'administrative_district_level_1' => 'TX',
                        'postal_code' => '11111',
                        'country' => 'US',
                    ],
                ],
            ],
        ];

        $result = $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::SHIPMENT, $result->type);
        $this->assertInstanceOf(ShipmentDetails::class, $result->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $result->recipient);
        $this->assertEquals('Bob Smith', $result->recipient->display_name);
        $this->assertEquals('bob@example.com', $result->recipient->email_address);
    }

    /**
     * Test creating fulfillment from array without type throws exception.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_without_type(): void
    {
        $fulfillmentArray = [
            'delivery_details' => [
                'schedule_type' => 'ASAP',
                'placed_at' => now()->toDateTimeString(),
                'deliver_at' => now()->addHour()->toDateTimeString(),
                'note' => 'Test delivery note',
            ],
        ];

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('"type" property for object Fulfillment is missing');

        $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);
    }

    /**
     * Test creating fulfillment from array with invalid type throws exception.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_with_invalid_type(): void
    {
        $fulfillmentArray = [
            'type' => 'INVALID_TYPE',
            'delivery_details' => [
                'schedule_type' => 'ASAP',
                'placed_at' => now()->toDateTimeString(),
                'deliver_at' => now()->addHour()->toDateTimeString(),
                'note' => 'Test delivery note',
            ],
        ];

        $this->expectException(InvalidSquareOrderException::class);
        $this->expectExceptionMessage('Invalid fulfillment type');

        $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);
    }

    /**
     * Test creating fulfillment from array without corresponding details throws exception.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_without_delivery_details(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::DELIVERY,
        ];

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('delivery_details property for object Fulfillment is missing');

        $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);
    }

    /**
     * Test creating fulfillment from array without pickup details throws exception.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_without_pickup_details(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::PICKUP,
        ];

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('pickup_details property for object Fulfillment is missing');

        $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);
    }

    /**
     * Test creating fulfillment from array without shipment details throws exception.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_without_shipment_details(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::SHIPMENT,
        ];

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('shipment_details property for object Fulfillment is missing');

        $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);
    }

    /**
     * Test creating fulfillment from array with recipient using customer_id.
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_with_customer_id_recipient(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::DELIVERY,
            'delivery_details' => [
                'schedule_type' => 'ASAP',
                'placed_at' => now()->toDateTimeString(),
                'deliver_at' => now()->addHour()->toDateTimeString(),
                'note' => 'Test delivery note',
                'recipient' => [
                    'customer_id' => $this->data->customer->id,
                ],
            ],
        ];

        $result = $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::DELIVERY, $result->type);
        $this->assertInstanceOf(DeliveryDetails::class, $result->fulfillmentDetails);
        $this->assertInstanceOf(Recipient::class, $result->recipient);
        $this->assertEquals($this->data->customer->id, $result->recipient->customer_id);
    }

    /**
     * Test creating fulfillment from model without recipient succeeds (recipient is optional).
     *
     * @return void
     */
    public function test_create_fulfillment_from_model_without_recipient(): void
    {
        $deliveryDetails = factory(DeliveryDetails::class)->create();
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make([
            'type' => FulfillmentType::DELIVERY,
        ]);
        $fulfillment->fulfillmentDetails()->associate($deliveryDetails);

        // No recipient is set
        $fulfillment->recipient = null;

        $result = $this->builder->createFulfillmentFromModel($fulfillment, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::DELIVERY, $result->type);
        $this->assertInstanceOf(DeliveryDetails::class, $result->fulfillmentDetails);
        $this->assertNull($result->recipient);
    }

    /**
     * Test creating fulfillment from array without recipient succeeds (recipient is optional).
     *
     * @return void
     */
    public function test_create_fulfillment_from_array_without_recipient(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::DELIVERY,
            'delivery_details' => [
                'schedule_type' => 'ASAP',
                'placed_at' => now()->toDateTimeString(),
                'deliver_at' => now()->addHour()->toDateTimeString(),
                'note' => 'Test delivery note',
            ],
        ];

        $result = $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertEquals(FulfillmentType::DELIVERY, $result->type);
        $this->assertInstanceOf(DeliveryDetails::class, $result->fulfillmentDetails);
        $this->assertNull($result->recipient);
    }

    /**
     * Test that recipient gets proper fulfillment_id relationship.
     *
     * @return void
     */
    public function test_recipient_fulfillment_relationship(): void
    {
        $fulfillmentArray = [
            'type' => FulfillmentType::DELIVERY,
            'delivery_details' => [
                'schedule_type' => 'ASAP',
                'placed_at' => now()->toDateTimeString(),
                'deliver_at' => now()->addHour()->toDateTimeString(),
                'note' => 'Test delivery note',
                'recipient' => [
                    'display_name' => 'John Doe',
                    'email_address' => 'john@example.com',
                    'phone_number' => '+1234567890',
                    'address' => [
                        'address_line_1' => '123 Main St',
                        'locality' => 'Anytown',
                        'administrative_district_level_1' => 'CA',
                        'postal_code' => '12345',
                        'country' => 'US',
                    ],
                ],
            ],
        ];

        $result = $this->builder->createFulfillmentFromArray($fulfillmentArray, $this->data->order);

        $this->assertInstanceOf(Fulfillment::class, $result);
        $this->assertInstanceOf(Recipient::class, $result->recipient);

        // Verify the one-to-one relationship is properly established
        $this->assertEquals($result->id, $result->recipient->fulfillment_id);
    }
}
