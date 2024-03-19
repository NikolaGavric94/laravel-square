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
use Nikolag\Square\Tests\Models\Fulfillment;
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
}
