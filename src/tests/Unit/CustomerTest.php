<?php

namespace Nikolag\Square\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Nikolag\Square\Customer;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use DatabaseMigrations, DatabaseTransactions, WithoutMiddleware;

    /**
     * User shouldn't have a customer.
     *
     * @return void
     */
    public function testHasCustomer()
    {
        $customer = factory(Customer::class)->create([
        	'email' => 'nikola.gavric94@gmail.com'
        ]);
        $this->assertDatabaseHas('nikolag_customers', [
        	'email' => 'nikola.gavric94@gmail.com'
        ]);
    }
}
