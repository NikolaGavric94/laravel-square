<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\Address;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Recipient;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\TestDataHolder;
use Square\Models\Address as SquareAddress;

class AddressTest extends TestCase
{
    private TestDataHolder $data;
    private Recipient $recipient;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->data = TestDataHolder::create();
    }

    /**
     * Address creation test.
     *
     * @return void
     */
    public function test_address_make(): void
    {
        $address = factory(Address::class)->create();

        $this->assertNotNull($address, 'Address is null.');
        $this->assertInstanceOf(Address::class, $address);
    }

    /**
     * Address persisting test.
     *
     * @return void
     */
    public function test_address_create(): void
    {
        $addressLine1 = '300 N State St';
        $locality = 'Chicago';
        $postalCode = '60654';

        $address = factory(Address::class)->create([
            'address_line_1' => $addressLine1,
            'locality' => $locality,
            'postal_code' => $postalCode,
        ]);

        $this->assertDatabaseHas('nikolag_addresses', [
            'address_line_1' => $addressLine1,
            'locality' => $locality,
            'postal_code' => $postalCode,
        ]);
    }

    /**
     * Test deleting Address cascades properly.
     *
     * @return void
     */
    public function test_delete_address(): void
    {
        $this->data->customer->address()->save($this->data->address);

        $addressId = $this->data->address->id;
        $this->data->address->delete();

        $this->assertDatabaseMissing('nikolag_addresses', [
            'id' => $addressId,
        ]);

        $this->data->customer->refresh();
        $this->assertNull($this->data->customer->address);
    }

    /**
     * Test Address factory creates valid US addresses.
     *
     * @return void
     */
    public function test_address_factory_creates_us_addresses(): void
    {
        $this->assertEquals('US', $this->data->address->country);
        $this->assertNotNull($this->data->address->address_line_1);
        $this->assertNotNull($this->data->address->locality);
        $this->assertNotNull($this->data->address->administrative_district_level_1);
        $this->assertNotNull($this->data->address->postal_code);
    }

    /**
     * Test converting Address to Square Address object.
     *
     * @return void
     */
    public function test_address_to_square_address(): void
    {
        $address = factory(Address::class)->create([
            'address_line_1' => '300 N State St',
            'address_line_2' => 'Unit 4629',
            'locality' => 'Chicago',
            'administrative_district_level_1' => 'IL',
            'postal_code' => '60654',
            'country' => 'US',
        ]);

        $squareAddress = $address->toSquareAddress();

        $this->assertInstanceOf(SquareAddress::class, $squareAddress);
        $this->assertEquals('300 N State St', $squareAddress->getAddressLine1());
        $this->assertEquals('Unit 4629', $squareAddress->getAddressLine2());
        $this->assertEquals('Chicago', $squareAddress->getLocality());
        $this->assertEquals('IL', $squareAddress->getAdministrativeDistrictLevel1());
        $this->assertEquals('60654', $squareAddress->getPostalCode());
        $this->assertEquals('US', $squareAddress->getCountry());
    }

    /**
     * Test creating Address from Square Address object.
     *
     * @return void
     */
    public function test_address_from_square_address(): void
    {
        $squareAddress = new SquareAddress();
        $squareAddress->setAddressLine1('300 N State St');
        $squareAddress->setAddressLine2('Unit 4629');
        $squareAddress->setLocality('Chicago');
        $squareAddress->setAdministrativeDistrictLevel1('IL');
        $squareAddress->setPostalCode('60654');
        $squareAddress->setCountry('US');

        $address = Address::fromSquareAddress($squareAddress);

        $this->assertInstanceOf(Address::class, $address);
        $this->assertEquals('300 N State St', $address->address_line_1);
        $this->assertEquals('Unit 4629', $address->address_line_2);
        $this->assertEquals('Chicago', $address->locality);
        $this->assertEquals('IL', $address->administrative_district_level_1);
        $this->assertEquals('60654', $address->postal_code);
        $this->assertEquals('US', $address->country);
    }

    /**
     * Test updating Address from Square Address object.
     *
     * @return void
     */
    public function test_address_update_from_square_address(): void
    {
        $address = factory(Address::class)->create([
            'address_line_1' => 'Old Address',
            'locality' => 'Old City',
        ]);

        $squareAddress = new SquareAddress();
        $squareAddress->setAddressLine1('300 N State St');
        $squareAddress->setAddressLine2('Unit 4629');
        $squareAddress->setLocality('Chicago');
        $squareAddress->setAdministrativeDistrictLevel1('IL');
        $squareAddress->setPostalCode('60654');
        $squareAddress->setCountry('US');

        $address->updateFromSquareAddress($squareAddress);

        $this->assertEquals('300 N State St', $address->address_line_1);
        $this->assertEquals('Unit 4629', $address->address_line_2);
        $this->assertEquals('Chicago', $address->locality);
        $this->assertEquals('IL', $address->administrative_district_level_1);
        $this->assertEquals('60654', $address->postal_code);
        $this->assertEquals('US', $address->country);
    }

    /**
     * Test Address belongs to Customer via polymorphic relationship.
     *
     * @return void
     */
    public function test_address_belongs_to_customer(): void
    {
        $this->data->customer->address()->save($this->data->address);
        $this->data->address->refresh();

        $this->assertNotNull($this->data->address->addressable_id);
        $this->assertNotNull($this->data->address->addressable_type);
        $this->assertEquals($this->data->customer->id, $this->data->address->addressable_id);
        $this->assertEquals(Customer::class, $this->data->address->addressable_type);
        $this->assertInstanceOf(Customer::class, $this->data->address->addressable);
        $this->assertEquals($this->data->customer->id, $this->data->address->addressable->id);
    }

    /**
     * Test Customer has one Address.
     *
     * @return void
     */
    public function test_customer_has_one_address(): void
    {
        $this->data->customer->address()->save($this->data->address);

        $this->assertInstanceOf(Address::class, $this->data->customer->address);
        $this->assertEquals($this->data->address->id, $this->data->customer->address->id);
        $this->assertEquals($this->data->address->address_line_1, $this->data->customer->address->address_line_1);
        $this->assertEquals($this->data->address->locality, $this->data->customer->address->locality);
    }

    /**
     * Test creating Customer with Address in one operation.
     *
     * @return void
     */
    public function test_customer_create_with_address(): void
    {
        $address = factory(Address::class)->make([
            'address_line_1' => '300 N State St',
            'locality' => 'Chicago',
            'postal_code' => '60654',
        ]);

        $this->data->customer->address()->save($address);

        $this->assertDatabaseHas('nikolag_addresses', [
            'addressable_type' => Customer::class,
            'addressable_id' => $this->data->customer->id,
            'address_line_1' => '300 N State St',
            'locality' => 'Chicago',
        ]);
    }
}
