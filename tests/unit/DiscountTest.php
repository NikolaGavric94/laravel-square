<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\Discount;
use Nikolag\Square\Tests\TestCase;

class DiscountTest extends TestCase
{
    /**
     * Discount creation.
     *
     * @return void
     */
    public function test_discount_make()
    {
        $discount = factory(Discount::class)->create();

        $this->assertNotNull($discount, 'Discount is null.');
    }

    /**
     * Discount persisting.
     *
     * @return void
     */
    public function test_discount_create()
    {
        $name = $this->faker->name;

        $tax = factory(Discount::class)->create([
            'name' => $name,
        ]);

        $this->assertDatabaseHas('nikolag_discounts', [
            'name' => $name,
        ]);
    }
}
