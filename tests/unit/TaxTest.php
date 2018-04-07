<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\Tax;
use Nikolag\Square\Tests\TestCase;

class TaxTest extends TestCase
{

    /**
     * Tax creation.
     *
     * @return void
     */
    public function test_tax_make()
    {
        $tax = factory(Tax::class)->create();

        $this->assertNotNull($tax, 'Tax is null.');
    }

    /**
     * Tax persisting.
     *
     * @return void
     */
    public function test_tax_create()
    {
        $name = $this->faker->name;

        $tax = factory(Tax::class)->create([
            'name' => $name
        ]);

        $this->assertDatabaseHas('nikolag_taxes', [
            'name' => $name
        ]);
    }
}