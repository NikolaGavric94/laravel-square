<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Nikolag\Square\Exception;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\TestCase;
use SquareConnect\ApiException;
use SquareConnect\Model\ChargeResponse;


class CustomerTest extends TestCase
{

    /**
     * Customer creation.
     * 
     * @return void
     */
    public function test_customer_make()
    {
        $customer = factory(Customer::class)->make();

        $this->assertNotNull($customer);
    }

    /**
     * Customer persisting.
     * 
     * @return void
     */
    public function test_customer_create()
    {
        $email = $this->faker->email;

        $customer = factory(Customer::class)->create([
            'email' => $email
        ]);

        $this->assertDatabaseHas('nikolag_customers', [
            'email' => $email
        ]);
    }

    /**
     * Listing transcations for customers.
     * 
     * @return void
     */
    public function test_customers_have_transactions()
    {
        $customers = factory(Customer::class, 25)
            ->create()
            ->each(function ($customer) {
                $customer->transactions()->save(factory(Transaction::class)->make());
            });
        $customer = $customers->random();

        $this->assertCount(25, Transaction::all());
        $this->assertNotEmpty($customer->transactions);
        $this->assertCount(1, $customer->transactions);
    }

    /**
     * List transactions.
     * 
     * @return void
     */
    public function test_customer_transaction_list()
    {
        $customer = factory(Customer::class)->make();
        $collection = $customer->transactions;

        $this->assertEmpty($collection);
        $this->assertTrue($collection->isEmpty());
    }

    /**
     * Charge OK.
     * @return void
     */
    public function test_customer_charge_ok()
    {
        $customer = factory(Customer::class)->make();
        $response = $customer->charge(5000, 'fake-card-nonce-ok', env('SQUARE_LOCATION'));

        $this->assertTrue($response instanceof ChargeResponse);
        $this->assertEquals($response->getTransaction()->getTenders()[0]->getAmountMoney()->getAmount(), 5000);
        $this->assertEquals($response->getTransaction()->getLocationId(), env('SQUARE_LOCATION'));
    }

    /**
     * Charge with non existing nonce.
     * 
     * @expectedException \SquareConnect\ApiException
     * @expectedExceptionCode 404
     * @return void
     */
    public function test_customer_charge_wrong_nonce()
    {
        $customer = factory(Customer::class)->make();
        $response = $customer->charge(5000, 'not-existant-nonce', env('SQUARE_LOCATION'));
        
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);
    }

    /**
     * Charge with wrong CVV.
     * 
     * @expectedException \SquareConnect\ApiException
     * @expectedExceptionCode 402
     * @return void
     */
    public function test_customer_charge_wrong_cvv()
    {
        $customer = factory(Customer::class)->make();
        $response = $customer->charge(5000, 'fake-card-nonce-rejected-cvv', env('SQUARE_LOCATION'));

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(402);
    }

    /**
     * Charge with wrong Postal Code.
     * 
     * @expectedException \SquareConnect\ApiException
     * @expectedExceptionCode 402
     * @return void
     */
    public function test_customer_charge_wrong_postal()
    {
        $customer = factory(Customer::class)->make();
        $response = $customer->charge(5000, 'fake-card-nonce-rejected-postalcode', env('SQUARE_LOCATION'));

        
        
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(402);
    }

    /**
     * Charge with wrong Expiration date.
     * 
     * @expectedException \SquareConnect\ApiException
     * @expectedExceptionCode 400
     * @return void
     */
    public function test_customer_charge_wrong_expiration_date()
    {
        $customer = factory(Customer::class)->make();
        $response = $customer->charge(5000, 'fake-card-nonce-rejected-expiration', env('SQUARE_LOCATION'));
        
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);
    }

    /**
     * Charge declined.
     * 
     * @expectedException \SquareConnect\ApiException
     * @expectedExceptionCode 400
     * @return void
     */
    public function test_customer_charge_declined()
    {
        $customer = factory(Customer::class)->make();
        $response = $customer->charge(5000, 'fake-card-nonce-rejected-expiration', env('SQUARE_LOCATION'));
        
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);
    }

    /**
     * Charge with already used nonce.
     * 
     * @expectedException \SquareConnect\ApiException
     * @expectedExceptionCode 400
     * @return void
     */
    public function test_customer_charge_used_nonce()
    {
        $customer = factory(Customer::class)->make();
        $response = $customer->charge(5000, 'fake-card-nonce-already-used', env('SQUARE_LOCATION'));
        
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);
    }
}
