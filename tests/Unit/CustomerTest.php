<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Nikolag\Square\Exception;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\User;
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
        $customer = factory(Customer::class)->create();

        $this->assertNotNull($customer, 'Customer is null.');
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
        $user = factory(User::class)->create();
        $customers = factory(Customer::class, 25)
            ->create()
            ->each(function ($customer) {
                $customer->transactions()->save(factory(Transaction::class)->create());
            });
        $customer = $customers->random();

        $this->assertCount(25, Transaction::all(), 'Number of transactions is not 25.');
        $this->assertNotEmpty($customer->transactions, 'Transactions are not empty.');
        $this->assertCount(1, $customer->transactions, 'Transactions count tied with Customer is not 1.');
    }

    /**
     * List transactions.
     * 
     * @return void
     */
    public function test_customer_transaction_list()
    {
        $customer = factory(Customer::class)->create();
        $collection = $customer->transactions;

        $this->assertEmpty($collection, 'List of customers is not empty.');
        $this->assertTrue($collection->isEmpty(), 'List of customers is not empty.');
    }
}
