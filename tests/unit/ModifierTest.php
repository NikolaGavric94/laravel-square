<?php

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Models\Modifier;
use Nikolag\Square\Tests\TestCase;

class ModifierTest extends TestCase
{
    /**
     * Modifier creation.
     *
     * @return void
     */
    public function test_modifier_make(): void
    {
        $modifier = factory(Modifier::class)->make();

        $this->assertNotNull($modifier, 'Modifier is null.');
    }

    /**
     * Modifier persisting.
     *
     * @return void
     */
    public function test_modifier_create(): void
    {
        $name = $this->faker->name;

        $modifier = factory(Modifier::class)->create([
            'name' => $name,
        ]);

        $this->assertDatabaseHas('nikolag_modifiers', [
            'name' => $name,
        ]);
    }
}
