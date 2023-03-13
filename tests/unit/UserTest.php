<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exception;
use Nikolag\Square\Models\Customer;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\Models\User;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class UserTest extends TestCase
{
    /**
     * Save customer and relate it to user.
     *
     * @return void
     */
    public function test_user_save_customer(): void
    {
        $user = factory(User::class)->create();
        $customer = [
            'payment_service_id' => null,
            'first_name'         => $this->faker->unique()->firstNameMale,
            'last_name'          => $this->faker->unique()->lastName,
            'company_name'       => $this->faker->unique()->address,
            'nickname'           => $this->faker->unique()->firstNameFemale,
            'email'              => $this->faker->unique()->companyEmail,
            'phone'              => $this->faker->unique()->tollFreePhoneNumber,
            'note'               => $this->faker->unique()->paragraph(5),
            'owner_id'           => null,
        ];

        $user->saveCustomer($customer);

        $this->assertNotEmpty($user->customers, 'List of customers tied with owner is empty.');
        $this->assertCount(1, $user->customers, 'Number of customers in the list is not 1.');
        $this->assertEquals($customer['email'], $user->customers->get(0)->email, 'Customer email doesn\'t match');
    }

    /**
     * Multiple customers saved.
     *
     * @return void
     */
    public function test_user_saves_multiple_customers(): void
    {
        $user = factory(User::class)->create();

        $customers = factory(Customer::class, 25)
            ->create()
            ->each(function ($customer) {
                $customer->transactions()->save(factory(Transaction::class)->make());
            });

        $user->customers()->saveMany($customers->take(11));

        $this->assertNotEmpty($user->customers, 'List of customers tied with owner is not empty.');
        $this->assertCount(11, $user->customers, 'Number of customers in the list is not 25.');
    }

    /**
     * Charge OK.
     *
     * @return void
     */
    public function test_user_charge_ok(): void
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'cnon:card-nonce-ok', env('SQUARE_LOCATION'));

        $this->assertTrue($response instanceof Transaction, 'Response is not of type Transaction.');
        $this->assertEquals($response->amount, 5000, 'Transaction amount is not 5000.');
        $this->assertEquals($response->status, Constants::TRANSACTION_STATUS_PASSED, 'Transaction status is not PASSED');
    }

    /**
     * Charge with non existing nonce.
     *
     * @return void
     */
    public function test_user_charge_wrong_nonce(): void
    {
        $user = factory(User::class)->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Invalid source/i');
        $this->expectExceptionCode(400);

        $user->charge(5000, 'not-existent-nonce', env('SQUARE_LOCATION'));
    }

    /**
     * Charge with wrong CVV.
     *
     * @return void
     */
    public function test_user_charge_wrong_cvv(): void
    {
        $user = factory(User::class)->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_CVV.'/i');
        $this->expectExceptionCode(400);

        $user->charge(5000, 'cnon:card-nonce-rejected-cvv', env('SQUARE_LOCATION'));
    }

    /**
     * Charge with wrong Postal Code.
     *
     * @return void
     */
    public function test_user_charge_wrong_postal(): void
    {
        $user = factory(User::class)->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_POSTAL_CODE.'/i');
        $this->expectExceptionCode(400);

        $user->charge(5000, 'cnon:card-nonce-rejected-postalcode', env('SQUARE_LOCATION'));
    }

    /**
     * Charge with wrong Expiration date.
     *
     * @return void
     */
    public function test_user_charge_wrong_expiration_date(): void
    {
        $user = factory(User::class)->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::INVALID_EXPIRATION.'/i');
        $this->expectExceptionCode(400);

        $user->charge(5000, 'cnon:card-nonce-rejected-expiration', env('SQUARE_LOCATION'));
    }

    /**
     * Charge declined.
     *
     * @return void
     */
    public function test_user_charge_declined(): void
    {
        $user = factory(User::class)->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::INVALID_EXPIRATION.'/i');
        $this->expectExceptionCode(400);

        $user->charge(5000, 'cnon:card-nonce-rejected-expiration', env('SQUARE_LOCATION'));
    }

    /**
     * Charge with already used nonce.
     *
     * @return void
     */
    public function test_user_charge_used_nonce(): void
    {
        $user = factory(User::class)->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/'.Constants::VERIFY_CVV.'/i');
        $this->expectExceptionCode(400);

        $user->charge(5000, 'cnon:card-nonce-rejected-cvv', env('SQUARE_LOCATION'));
    }

    /**
     * Charge with non-existant currency.
     *
     * @return void
     */
    public function test_user_charge_non_existant_currency(): void
    {
        $user = factory(User::class)->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/This merchant can only process payments in USD, but amount was provided in XXX/i');
        $this->expectExceptionCode(400);

        $user->charge(5000, 'cnon:card-nonce-rejected-cvv', env('SQUARE_LOCATION'), [], null, 'XXX');
    }
}
