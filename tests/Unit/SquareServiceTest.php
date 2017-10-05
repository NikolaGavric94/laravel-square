<?php

namespace Nikolag\Square\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Nikolag\Square\Exception;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;
use SquareConnect\ApiException;

class SquareServiceTest extends TestCase
{
    /**
     * Charge OK.
     * @return void
     */
    public function test_square_charge_ok()
    {
        $response = Square::charge(['amount' => 5000, 'card_nonce' => 'fake-card-nonce-ok', 'location_id' => env('SQUARE_LOCATION')]);

        $this->assertTrue($response instanceof Transaction, 'Response is not of type Transaction.');
        $this->assertEquals($response->amount, 5000, 'Transaction amount is not 5000.');
        $this->assertEquals($response->status, Constants::TRANSACTION_STATUS_PASSED, 'Transaction status is not PASSED');
    }

    /**
     * Charge with non existing nonce.
     *
     * @expectedException \SquareConnect\ApiException
     * @expectedExceptionCode 404
     * @return void
     */
    public function test_square_charge_wrong_nonce()
    {
        $response = Square::charge(['amount' => 5000, 'card_nonce' => 'not-existant-nonce', 'location_id' => env('SQUARE_LOCATION')]);
        
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
    public function test_square_charge_wrong_cvv()
    {
        $response = Square::charge(['amount' => 5000, 'card_nonce' => 'fake-card-nonce-rejected-cvv', 'location_id' => env('SQUARE_LOCATION')]);

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
    public function test_square_charge_wrong_postal()
    {
        $response = Square::charge(['amount' => 5000, 'card_nonce' => 'fake-card-nonce-rejected-postalcode', 'location_id' => env('SQUARE_LOCATION')]);
        
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
    public function test_square_charge_wrong_expiration_date()
    {
        $response = Square::charge(['amount' => 5000, 'card_nonce' => 'fake-card-nonce-rejected-expiration', 'location_id' => env('SQUARE_LOCATION')]);
        
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
    public function test_square_charge_declined()
    {
        $response = Square::charge(['amount' => 5000, 'card_nonce' => 'fake-card-nonce-rejected-expiration', 'location_id' => env('SQUARE_LOCATION')]);
        
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
    public function test_square_charge_used_nonce()
    {
        $response = Square::charge(['amount' => 5000, 'card_nonce' => 'fake-card-nonce-already-used', 'location_id' => env('SQUARE_LOCATION')]);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);
    }

    /**
     * Charge with non-existant currency.
     *
     * @expectedException \SquareConnect\ApiException
     * @expectedExceptionCode 400
     * @return void
     */
    public function test_square_charge_non_existant_currency()
    {
        $response = Square::charge(['amount' => 5000, 'card_nonce' => 'fake-card-nonce-ok', 'location_id' => env('SQUARE_LOCATION'), 'currency' => 'XXX']);
        
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);
    }
}
