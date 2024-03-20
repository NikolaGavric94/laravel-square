<?php
/**
 * Created by PhpStorm.
 * User: mbingham
 * Date: 3/19/24
 * Time: 17:06.
 */

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class FulfillmentTest extends TestCase
{
    /**
     * Product creation.
     *
     * @return void
     */
    public function test_fulfillment_make(): void
    {
        $fulfillment = factory(Fulfillment::class)->create();

        $this->assertNotNull($fulfillment, 'Fulfillment is null.');
    }

    /**
     * Product persisting.
     *
     * @return void
     */
    public function test_fulfillment_create(): void
    {
        $name = $this->faker->name;

        $fulfillment = factory(Fulfillment::class)->create([
            'type' => Constants::FULFILLMENT_TYPE_PICKUP
        ]);

        $this->assertDatabaseHas('nikolag_fulfillments', [
            'type' => Constants::FULFILLMENT_TYPE_PICKUP,
        ]);
    }

    /**
     * Check fulfillment persisting with orders.
     *
     * @return void
     */
    public function test_fulfillment_create_with_orders(): void
    {
        $order = factory(Order::class)->create();

        /** @var Fulfillment $fulfillment */
        $fulfillment = factory(Fulfillment::class)->create([
            'type' => Constants::FULFILLMENT_TYPE_PICKUP
        ]);

        $fulfillment->order()->associate($order);

        $this->assertInstanceOf(Order::class, $fulfillment->order);
    }
}
