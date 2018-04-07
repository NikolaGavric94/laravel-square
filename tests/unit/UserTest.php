<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\InvalidSquareCurrencyException;
use Nikolag\Square\Exceptions\InvalidSquareCvvException;
use Nikolag\Square\Exceptions\InvalidSquareExpirationDateException;
use Nikolag\Square\Exceptions\InvalidSquareNonceException;
use Nikolag\Square\Exceptions\InvalidSquareZipcodeException;
use Nikolag\Square\Exceptions\UsedSquareNonceException;
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
    public function test_user_save_customer()
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
    public function test_user_saves_multiple_customers()
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
    public function test_user_charge_ok()
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'fake-card-nonce-ok', env('SQUARE_LOCATION'));

        $this->assertTrue($response instanceof Transaction, 'Response is not of type Transaction.');
        $this->assertEquals($response->amount, 5000, 'Transaction amount is not 5000.');
        $this->assertEquals($response->status, Constants::TRANSACTION_STATUS_PASSED, 'Transaction status is not PASSED');
    }

    /**
     * Charge with non existing nonce.
     *
     * @expectedException \Nikolag\Square\Exceptions\InvalidSquareNonceException
     * @expectedExceptionCode 404
     *
     * @return void
     */
    public function test_user_charge_wrong_nonce()
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'not-existant-nonce', env('SQUARE_LOCATION'));

        $this->expectException(InvalidSquareNonceException::class);
        $this->expectExceptionCode(404);
    }

    /**
     * Charge with wrong CVV.
     *
     * @expectedException \Nikolag\Square\Exceptions\InvalidSquareCvvException
     * @expectedExceptionCode 402
     *
     * @return void
     */
    public function test_user_charge_wrong_cvv()
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'fake-card-nonce-rejected-cvv', env('SQUARE_LOCATION'));

        $this->expectException(InvalidSquareCvvException::class);
        $this->expectExceptionCode(402);
    }

    /**
     * Charge with wrong Postal Code.
     *
     * @expectedException \Nikolag\Square\Exceptions\InvalidSquareZipcodeException
     * @expectedExceptionCode 402
     *
     * @return void
     */
    public function test_user_charge_wrong_postal()
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'fake-card-nonce-rejected-postalcode', env('SQUARE_LOCATION'));

        $this->expectException(InvalidSquareZipcodeException::class);
        $this->expectExceptionCode(402);
    }

    /**
     * Charge with wrong Expiration date.
     *
     * @expectedException \Nikolag\Square\Exceptions\InvalidSquareExpirationDateException
     * @expectedExceptionCode 400
     *
     * @return void
     */
    public function test_user_charge_wrong_expiration_date()
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'fake-card-nonce-rejected-expiration', env('SQUARE_LOCATION'));

        $this->expectException(InvalidSquareExpirationDateException::class);
        $this->expectExceptionCode(400);
    }

    /**
     * Charge declined.
     *
     * @expectedException \Nikolag\Square\Exceptions\InvalidSquareExpirationDateException
     * @expectedExceptionCode 400
     *
     * @return void
     */
    public function test_user_charge_declined()
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'fake-card-nonce-rejected-expiration', env('SQUARE_LOCATION'));

        $this->expectException(InvalidSquareExpirationDateException::class);
        $this->expectExceptionCode(400);
    }

    /**
     * Charge with already used nonce.
     *
     * @expectedException \Nikolag\Square\Exceptions\UsedSquareNonceException
     * @expectedExceptionCode 400
     *
     * @return void
     */
    public function test_user_charge_used_nonce()
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'fake-card-nonce-already-used', env('SQUARE_LOCATION'));

        $this->expectException(UsedSquareNonceException::class);
        $this->expectExceptionCode(400);
    }

    /**
     * Charge with non-existant currency.
     *
     * @expectedException \Nikolag\Square\Exceptions\InvalidSquareCurrencyException
     * @expectedExceptionCode 400
     *
     * @return void
     */
    public function test_user_charge_non_existant_currency()
    {
        $user = factory(User::class)->create();
        $response = $user->charge(5000, 'fake-card-nonce-already-used', env('SQUARE_LOCATION'), null, 'XXX');

        $this->expectException(InvalidSquareCurrencyException::class);
        $this->expectExceptionCode(400);
    }
}
