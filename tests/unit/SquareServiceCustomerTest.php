<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nikolag\Square\Exception;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Address;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Tests\Traits\MocksSquareConfigDependency;
use Square\Models\Address as SquareAddress;
use Square\Models\Builders\AddressBuilder;
use Square\Models\Builders\CustomerPreferencesBuilder;
use Square\Models\CustomerCreationSource;

class SquareServiceCustomerTest extends TestCase
{
    use RefreshDatabase, MocksSquareConfigDependency;

    private User $merchant;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->merchant = factory(User::class)->create();
    }

    /**
     * Test creating a new customer successfully stores payment_service_id and version.
     */
    public function test_create_customer_stores_payment_service_id_and_version(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);

        // Mock the create customer API call
        $this->mockCreateCustomerSuccess([
            'id' => 'CUSTOMER_TEST_123',
            'givenName' => 'John',
            'familyName' => 'Doe',
            'emailAddress' => 'john.doe@example.com',
            'version' => 1,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer via Square facade
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Refresh customer to get updated values
        $customer->refresh();

        // Verify payment_service_id and version are stored
        $this->assertNotNull($customer->payment_service_id);
        $this->assertEquals('CUSTOMER_TEST_123', $customer->payment_service_id);
        $this->assertEquals(1, $customer->payment_service_version);

        // Verify customer is saved to database
        $this->assertDatabaseHas('nikolag_customers', [
            'payment_service_id' => 'CUSTOMER_TEST_123',
            'payment_service_version' => 1,
            'email' => 'john.doe@example.com',
        ]);
    }

    /**
     * Test creating a new customer with creation_source field.
     */
    public function test_create_customer_stores_creation_source(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
        ]);

        // Mock the create customer API call with creation_source
        $this->mockCreateCustomerSuccess([
            'id' => 'CUSTOMER_TEST_456',
            'givenName' => 'Jane',
            'familyName' => 'Smith',
            'emailAddress' => 'jane.smith@example.com',
            'version' => 1,
            'creationSource' => CustomerCreationSource::THIRD_PARTY,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Refresh customer to get updated values
        $customer->refresh();

        // Verify creation_source is stored
        $this->assertEquals(CustomerCreationSource::THIRD_PARTY, $customer->creation_source);

        $this->assertDatabaseHas('nikolag_customers', [
            'payment_service_id' => 'CUSTOMER_TEST_456',
            'creation_source' => CustomerCreationSource::THIRD_PARTY,
        ]);
    }

    /**
     * Test creating a new customer with address syncs address from Square response.
     */
    public function test_create_customer_syncs_address_from_square_response(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'email' => 'bob.johnson@example.com',
        ]);

        // Build Square address
        $squareAddress = AddressBuilder::init()
            ->addressLine1('123 Main St')
            ->addressLine2('Apt 4')
            ->locality('Chicago')
            ->administrativeDistrictLevel1('IL')
            ->postalCode('60601')
            ->country('US')
            ->build();

        // Mock the create customer API call with address
        $this->mockCreateCustomerSuccess([
            'id' => 'CUSTOMER_TEST_789',
            'givenName' => 'Bob',
            'familyName' => 'Johnson',
            'emailAddress' => 'bob.johnson@example.com',
            'version' => 1,
            'address' => $squareAddress,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Refresh customer to load relationship
        $customer->refresh();

        // Verify address is created and associated
        $this->assertNotNull($customer->address);
        $this->assertEquals('123 Main St', $customer->address->address_line_1);
        $this->assertEquals('Apt 4', $customer->address->address_line_2);
        $this->assertEquals('Chicago', $customer->address->locality);
        $this->assertEquals('IL', $customer->address->administrative_district_level_1);
        $this->assertEquals('60601', $customer->address->postal_code);
        $this->assertEquals('US', $customer->address->country);

        // Verify address is in database
        $this->assertDatabaseHas('nikolag_addresses', [
            'addressable_type' => Customer::class,
            'addressable_id' => $customer->id,
            'address_line_1' => '123 Main St',
            'locality' => 'Chicago',
        ]);
    }

    /**
     * Test creating a new customer with preferences syncs from Square response.
     */
    public function test_create_customer_syncs_preferences_from_square_response(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'Alice',
            'last_name' => 'Williams',
            'email' => 'alice.williams@example.com',
        ]);

        // Build preferences
        $preferences = CustomerPreferencesBuilder::init()
            ->emailUnsubscribed(true)
            ->build();

        // Mock the create customer API call with preferences
        $this->mockCreateCustomerSuccess([
            'id' => 'CUSTOMER_TEST_101',
            'givenName' => 'Alice',
            'familyName' => 'Williams',
            'emailAddress' => 'alice.williams@example.com',
            'version' => 1,
            'preferences' => $preferences,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Refresh customer to get updated values
        $customer->refresh();

        // Verify preferences are stored
        $this->assertNotNull($customer->preferences);
        $this->assertTrue($customer->preferences['email_unsubscribed']);

        $this->assertDatabaseHas('nikolag_customers', [
            'payment_service_id' => 'CUSTOMER_TEST_101',
        ]);

        // Verify JSON field
        $freshCustomer = Customer::find($customer->id);
        $this->assertTrue($freshCustomer->preferences['email_unsubscribed']);
    }

    /**
     * Test creating a new customer with group_ids and segment_ids.
     */
    public function test_create_customer_syncs_group_and_segment_ids_from_square_response(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie.brown@example.com',
        ]);

        $groupIds = ['GROUP_1', 'GROUP_2'];
        $segmentIds = ['SEGMENT_A', 'SEGMENT_B'];

        // Mock the create customer API call with groups and segments
        $this->mockCreateCustomerSuccess([
            'id' => 'CUSTOMER_TEST_202',
            'givenName' => 'Charlie',
            'familyName' => 'Brown',
            'emailAddress' => 'charlie.brown@example.com',
            'version' => 1,
            'groupIds' => $groupIds,
            'segmentIds' => $segmentIds,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Refresh customer to get updated values
        $customer->refresh();

        // Verify groups and segments are stored
        $this->assertEquals($groupIds, $customer->group_ids);
        $this->assertEquals($segmentIds, $customer->segment_ids);

        // Verify in database
        $freshCustomer = Customer::find($customer->id);
        $this->assertEquals($groupIds, $freshCustomer->group_ids);
        $this->assertEquals($segmentIds, $freshCustomer->segment_ids);
    }

    /**
     * Test creating a customer fails with Square API error.
     */
    public function test_create_customer_handles_api_error(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'Error',
            'last_name' => 'Test',
            'email' => 'error@example.com',
        ]);

        // Mock API error
        $this->mockCreateCustomerError('Customer creation failed due to invalid email', 400);

        $this->expectException(Exception::class);

        // Attempt to save customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();
    }

    /**
     * Test updating an existing customer updates version number.
     */
    public function test_update_customer_syncs_version(): void
    {
        // Create customer with existing payment_service_id
        $customer = factory(Customer::class)->create([
            'first_name' => 'David',
            'last_name' => 'Miller',
            'email' => 'david.miller@example.com',
        ]);

        // Manually set guarded fields (bypassing mass assignment protection)
        $customer->payment_service_id = 'EXISTING_CUSTOMER_123';
        $customer->payment_service_version = 1;
        $customer->save();

        // Verify customer has payment_service_id
        $customer->refresh();
        $this->assertEquals('EXISTING_CUSTOMER_123', $customer->payment_service_id);
        $this->assertEquals(1, $customer->payment_service_version);

        // Mock the update customer API call
        $this->mockUpdateCustomerSuccess([
            'id' => 'EXISTING_CUSTOMER_123',
            'givenName' => 'David',
            'familyName' => 'Miller',
            'emailAddress' => 'david.miller@example.com', // Keep original email
            'version' => 2, // Version incremented
            'createdAt' => now()->subDay()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer (DO NOT change email before setCustomer - customerBuilder queries by email!)
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Verify version was incremented
        $customer->refresh();
        $this->assertEquals(2, $customer->payment_service_version);

        $this->assertDatabaseHas('nikolag_customers', [
            'payment_service_id' => 'EXISTING_CUSTOMER_123',
            'payment_service_version' => 2,
            'email' => 'david.miller@example.com',
        ]);
    }

    /**
     * Test updating an existing customer syncs address from Square response.
     */
    public function test_update_customer_syncs_updated_address_from_square_response(): void
    {
        // Create customer with existing address
        $customer = factory(Customer::class)->create([
            'first_name' => 'Emma',
            'last_name' => 'Davis',
            'email' => 'emma.davis@example.com',
        ]);

        // Manually set guarded fields (bypassing mass assignment protection)
        $customer->payment_service_id = 'EXISTING_CUSTOMER_456';
        $customer->payment_service_version = 1;
        $customer->save();

        $oldAddress = factory(Address::class)->create([
            'address_line_1' => 'Old Address St',
            'locality' => 'Old City',
        ]);
        $customer->address()->save($oldAddress);

        // Build updated Square address
        $updatedSquareAddress = AddressBuilder::init()
            ->addressLine1('456 New Ave')
            ->addressLine2('Suite 100')
            ->locality('New York')
            ->administrativeDistrictLevel1('NY')
            ->postalCode('10001')
            ->country('US')
            ->build();

        // Mock the update customer API call with new address
        // NOTE: Keep email same as original - CustomerBuilder queries by email!
        $this->mockUpdateCustomerSuccess([
            'id' => 'EXISTING_CUSTOMER_456',
            'givenName' => 'Emma',
            'familyName' => 'Davis',
            'emailAddress' => 'emma.davis@example.com', // Keep original email
            'version' => 2,
            'address' => $updatedSquareAddress,
            'createdAt' => now()->subDay()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer (triggers update)
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Refresh customer to load updated relationship
        $customer->refresh();

        // Verify address was updated
        $this->assertEquals('456 New Ave', $customer->address->address_line_1);
        $this->assertEquals('Suite 100', $customer->address->address_line_2);
        $this->assertEquals('New York', $customer->address->locality);
        $this->assertEquals('NY', $customer->address->administrative_district_level_1);
        $this->assertEquals('10001', $customer->address->postal_code);
        $this->assertEquals('US', $customer->address->country);
    }

    /**
     * Test updating an existing customer syncs preferences, groups, and segments.
     */
    public function test_update_customer_syncs_preferences_groups_segments_from_square_response(): void
    {
        // Create customer
        $customer = factory(Customer::class)->create([
            'first_name' => 'Frank',
            'last_name' => 'Wilson',
            'email' => 'frank.wilson@example.com',
        ]);

        // Manually set guarded fields (bypassing mass assignment protection)
        $customer->payment_service_id = 'EXISTING_CUSTOMER_789';
        $customer->payment_service_version = 1;
        $customer->save();

        // Build updated data
        $preferences = CustomerPreferencesBuilder::init()
            ->emailUnsubscribed(false)
            ->build();

        $updatedGroupIds = ['GROUP_3', 'GROUP_4'];
        $updatedSegmentIds = ['SEGMENT_C', 'SEGMENT_D'];

        // Mock the update customer API call
        $this->mockUpdateCustomerSuccess([
            'id' => 'EXISTING_CUSTOMER_789',
            'givenName' => 'Frank',
            'familyName' => 'Wilson',
            'emailAddress' => 'frank.wilson@example.com',
            'version' => 2,
            'preferences' => $preferences,
            'groupIds' => $updatedGroupIds,
            'segmentIds' => $updatedSegmentIds,
            'createdAt' => now()->subDay()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Refresh to get updated data from database
        $customer->refresh();

        // Verify updated data
        $this->assertFalse($customer->preferences['email_unsubscribed']);
        $this->assertEquals($updatedGroupIds, $customer->group_ids);
        $this->assertEquals($updatedSegmentIds, $customer->segment_ids);

        // Verify version incremented
        $this->assertEquals(2, $customer->payment_service_version);
    }

    /**
     * Test updating a customer fails with Square API error.
     */
    public function test_update_customer_handles_api_error(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'Error',
            'last_name' => 'Update',
            'email' => 'error.update@example.com',
        ]);

        // Manually set guarded fields (bypassing mass assignment protection)
        $customer->payment_service_id = 'EXISTING_CUSTOMER_ERROR';
        $customer->payment_service_version = 1;
        $customer->save();

        // Mock API error
        $this->mockUpdateCustomerError('Customer update failed', 400);

        $this->expectException(Exception::class);

        // Attempt to update customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();
    }

    /**
     * Test customer with birthday field is properly synced.
     */
    public function test_create_customer_with_birthday(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'Birthday',
            'last_name' => 'Test',
            'email' => 'birthday@example.com',
            'birthday' => '1990-05-15',
        ]);

        // Mock the create customer API call with birthday
        $this->mockCreateCustomerSuccess([
            'id' => 'CUSTOMER_BIRTHDAY_123',
            'givenName' => 'Birthday',
            'familyName' => 'Test',
            'emailAddress' => 'birthday@example.com',
            'birthday' => '1990-05-15',
            'version' => 1,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Verify birthday is stored
        $this->assertEquals('1990-05-15', $customer->birthday->format('Y-m-d'));

        $this->assertDatabaseHas('nikolag_customers', [
            'payment_service_id' => 'CUSTOMER_BIRTHDAY_123',
        ]);
    }

    /**
     * Test customer with reference_id field is properly synced.
     */
    public function test_create_customer_with_reference_id(): void
    {
        $customer = factory(Customer::class)->create([
            'first_name' => 'Reference',
            'last_name' => 'Test',
            'email' => 'reference@example.com',
            'reference_id' => 'EXTERNAL_REF_123',
        ]);

        // Mock the create customer API call with reference_id
        $this->mockCreateCustomerSuccess([
            'id' => 'CUSTOMER_REF_123',
            'givenName' => 'Reference',
            'familyName' => 'Test',
            'emailAddress' => 'reference@example.com',
            'referenceId' => 'EXTERNAL_REF_123',
            'version' => 1,
            'createdAt' => now()->toISOString(),
            'updatedAt' => now()->toISOString(),
        ]);

        // Save customer
        Square::setMerchant($this->merchant)
            ->setCustomer($customer)
            ->save();

        // Verify reference_id is stored
        $this->assertEquals('EXTERNAL_REF_123', $customer->reference_id);

        $this->assertDatabaseHas('nikolag_customers', [
            'payment_service_id' => 'CUSTOMER_REF_123',
            'reference_id' => 'EXTERNAL_REF_123',
        ]);
    }
}
