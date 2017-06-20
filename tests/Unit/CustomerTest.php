<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Customer;
use Nikolag\Square\Tests\TestCase;


class CustomerTest extends TestCase
{

    /**
     * Check if a user has a customer.
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
