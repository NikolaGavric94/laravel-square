<?php
/**
 * Created by PhpStorm.
 * User: nikola
 * Date: 6/20/18
 * Time: 02:33.
 */

namespace Nikolag\Square\Tests\Unit;

use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\OrderProductPivot;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Tests\Models\Order;
use Nikolag\Square\Tests\TestCase;
use Nikolag\Square\Utils\Constants;

class ProductTest extends TestCase
{
    /**
     * Product creation.
     *
     * @return void
     */
    public function test_product_make()
    {
        $product = factory(Product::class)->create();

        $this->assertNotNull($product, 'Product is null.');
    }

    /**
     * Product persisting.
     *
     * @return void
     */
    public function test_product_create()
    {
        $name = $this->faker->name;

        $product = factory(Product::class)->create([
            'name' => $name,
        ]);

        $this->assertDatabaseHas('nikolag_products', [
            'name' => $name,
        ]);
    }

    /**
     * Check product persisting with orders.
     *
     * @return void
     */
    public function test_product_create_with_orders()
    {
        $name = $this->faker->name;
        $order1 = factory(Order::class)->create();
        $order2 = factory(Order::class)->create();

        $product = factory(Product::class)->create([
            'name' => $name,
        ]);

        $product->orders()->attach([$order1->id, $order2->id]);

        $this->assertCount(2, $product->orders);
        $this->assertContainsOnlyInstancesOf(Order::class, $product->orders);
    }

    /**
     * Check product persisting with taxes.
     *
     * @return void
     */
    public function test_product_create_with_taxes()
    {
        $product = factory(Product::class)->create();
        $productPivot = factory(OrderProductPivot::class)->create();

        $tax1 = factory(Tax::class)->create();
        $tax2 = factory(Tax::class)->create();

        $productPivot->taxes()->attach([$tax1->id, $tax2->id], ['deductible_type' => Constants::TAX_NAMESPACE]);
        $productPivot->product()->associate($product);

        $this->assertCount(2, $productPivot->taxes);
        $this->assertContainsOnlyInstancesOf(Constants::TAX_NAMESPACE, $productPivot->taxes);
        $this->assertTrue($productPivot->hasTax($tax1));
        $this->assertTrue($productPivot->hasTax($tax2));

        $this->assertTrue($productPivot->hasProduct($product));
        $this->assertInstanceOf(Constants::PRODUCT_NAMESPACE, $productPivot->product);
    }

    /**
     * Order creation without location id, testing exception case.
     *
     * @return void
     */
    public function test_order_missing_location_id_exception()
    {
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create();

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Required field is missing');
        $this->expectExceptionCode(500);

        Square::setOrder($order, env('SQUARE_LOCATION'))->addProduct($product, 0);
    }
}
