<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Nikolag\Square\Exception;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use SquareConnect\ApiException;
use SquareConnect\Model\ChargeResponse;


class UserTest extends TestCase
{

    /**
     * Multiple customers saved.
     * 
     * @return void
     */
    public function test_user_saves_multiple_customers()
    {
        $user = factory(User::class)->create();
        
        $customers = factory(Customer::class, 25)
            ->create()
            ->each(function ($customer) {
                $customer->transactions()->save(factory(Transaction::class)->make());
            });

        $user->customers()->saveMany($customers);
        
        $this->assertNotEmpty($user->customers);
        $this->assertCount(25, $user->customers);
    }
}
