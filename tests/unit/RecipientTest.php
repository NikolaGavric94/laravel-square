<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\DeliveryDetails;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Square\Models\Address;
use Square\Models\FulfillmentType;

class RecipientTest extends TestCase
{
    private TestDataHolder $data;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->data = TestDataHolder::create();
    }

    /**
     * Tests building the Square request Address object.
     *
     * @return void
     */
    public function test_get_square_request_address(): void
    {
        $squareAddress = $this->data->fulfillmentRecipient->getSquareRequestAddress();

        $this->assertNotNull($squareAddress, 'Square Address is null.');

        // Make sure it's the correct type
        $this->assertInstanceOf(Address::class, $squareAddress);

        // Make sure the values are correct
        $originalAddress = $this->data->fulfillmentRecipient->address;
        $this->assertEquals($squareAddress->getAddressLine1(), $originalAddress['address_line_1'] ?? null);
        $this->assertEquals($squareAddress->getAddressLine2(), $originalAddress['address_line_2'] ?? null);
        $this->assertEquals($squareAddress->getAddressLine3(), $originalAddress['address_line_3'] ?? null);
        $this->assertEquals($squareAddress->getLocality(), $originalAddress['locality'] ?? null);
        $this->assertEquals($squareAddress->getSublocality(), $originalAddress['sublocality'] ?? null);
        $this->assertEquals($squareAddress->getSublocality2(), $originalAddress['sublocality_2'] ?? null);
        $this->assertEquals($squareAddress->getSublocality3(), $originalAddress['sublocality_3'] ?? null);
        $this->assertEquals($squareAddress->getAdministrativeDistrictLevel1(), $originalAddress['administrative_district_level_1'] ?? null);
        $this->assertEquals($squareAddress->getAdministrativeDistrictLevel2(), $originalAddress['administrative_district_level_2'] ?? null);
        $this->assertEquals($squareAddress->getAdministrativeDistrictLevel3(), $originalAddress['administrative_district_level_3'] ?? null);
        $this->assertEquals($squareAddress->getPostalCode(), $originalAddress['postal_code'] ?? null);
        $this->assertEquals($squareAddress->getCountry(), $originalAddress['country'] ?? null);
        $this->assertEquals($squareAddress->getFirstName(), $originalAddress['first_name'] ?? null);
        $this->assertEquals($squareAddress->getLastName(), $originalAddress['last_name'] ?? null);
    }

    /**
     * Test recipient fulfillment relationship.
     *
     * @return void
     */
    public function test_recipient_fulfillment_relationship(): void
    {
        // Create fulfillment with delivery details
        $delivery = factory(DeliveryDetails::class)->create();
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make();
        $fulfillment->fulfillmentDetails()->associate($delivery);

        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);
        $fulfillment->save();

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $recipient->fulfillment()->associate($fulfillment);
        $recipient->save();

        // Test the relationship
        $this->assertInstanceOf(Fulfillment::class, $recipient->fulfillment);
        $this->assertEquals($fulfillment->id, $recipient->fulfillment->id);
        $this->assertEquals($recipient->id, $fulfillment->recipient->id);
    }

    /**
     * Tests the recipient is deleted when the fulfillment is deleted.
     *
     * @return void
     */
    public function test_recipient_deleted_with_fulfillment(): void
    {
        // Create fulfillment with delivery details first
        $delivery = factory(DeliveryDetails::class)->create();
        $fulfillment = factory(Fulfillment::class)->states(FulfillmentType::DELIVERY)->make();
        $fulfillment->fulfillmentDetails()->associate($delivery);

        $order = factory(Order::class)->create();
        $fulfillment->order()->associate($order);
        $fulfillment->save();

        // Create a recipient and associate it with the fulfillment
        $recipient = factory(Recipient::class)->make();
        $recipient->fulfillment()->associate($fulfillment);
        $recipient->save();
        $this->assertInstanceOf(Recipient::class, $fulfillment->recipient);

        // Delete the fulfillment
        $fulfillment->delete();
        $this->assertNull($fulfillment->fresh());

        // Test the recipient is also deleted
        $this->assertNull($recipient->fresh());
    }
}
