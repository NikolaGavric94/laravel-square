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
use Nikolag\Square\Tests\TestDataHolder;
use Nikolag\Square\Utils\Constants;
use Nikolag\Square\Utils\Util;

class ProductTest extends TestCase
{
    private TestDataHolder $data;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->data = TestDataHolder::make();
    }
    /**
     * Product creation.
     *
     * @return void
     */
    public function test_product_make(): void
    {
        $product = factory(Product::class)->create();

        $this->assertNotNull($product, 'Product is null.');
    }

    /**
     * Product persisting.
     *
     * @return void
     */
    public function test_product_create(): void
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
    public function test_product_create_with_orders(): void
    {
        $name = 'Test Product ' . uniqid();
        $variation = 'Test Variation ' . uniqid();
        $order1 = factory(Order::class)->create();
        $order2 = factory(Order::class)->create();

        $product = factory(Product::class)->create([
            'name' => $name,
            'variation_name' => $variation,
            'price' => 10_00,
        ]);

        // Create order product pivots directly to have more control
        $orderProduct1 = new OrderProductPivot([
            'price' => 10_00,
            'quantity' => 1
        ]);
        $orderProduct1->order()->associate($order1);
        $orderProduct1->product()->associate($product);
        $orderProduct1->save();

        $orderProduct2 = new OrderProductPivot([
            'price' => 10_00,
            'quantity' => 1
        ]);
        $orderProduct2->order()->associate($order2);
        $orderProduct2->product()->associate($product);
        $orderProduct2->save();

        $this->assertCount(2, $product->orders);
        $this->assertContainsOnlyInstancesOf(Order::class, $product->orders);
    }

    /**
     * Check product persisting with taxes.
     *
     * @return void
     */
    public function test_product_create_with_taxes(): void
    {
        $product = factory(Product::class)->create();
        $productPivot = factory(OrderProductPivot::class)->create();

        $tax1 = factory(Tax::class)->create();
        $tax2 = factory(Tax::class)->create();

        $productPivot->taxes()->attach([$tax1->id, $tax2->id], ['deductible_type' => Constants::TAX_NAMESPACE, 'scope' => Constants::DEDUCTIBLE_SCOPE_PRODUCT]);
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
    public function test_order_missing_location_id_exception(): void
    {
        $order = factory(Order::class)->create();
        $product = factory(Product::class)->create();

        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessage('Required field is missing');
        $this->expectExceptionCode(500);

        Square::setOrder($order, env('SQUARE_LOCATION'))->addProduct($product, 0);
    }

    /**
     * Test variable item pricing when adding a product to an order
     *
     * @return void
     */
    public function test_variable_item_pricing_when_adding_product_to_order(): void
    {
        // Create a product with a base price (we'll override in the order)
        $uniqueId = uniqid();
        $variablePriceProduct = factory(Product::class)->create([
            'price' => 1000, // Base price in product record
            'name' => 'Variable Price Product ' . $uniqueId,
            'variation_name' => 'Test Variation ' . $uniqueId,
        ]);

        // Create an order
        $order = factory(Order::class)->create();

        // Create OrderProductPivot with variable pricing directly
        $orderProduct = new OrderProductPivot([
            'price' => 800, // Different price in the order than in the product
            'quantity' => 2  // Quantity is 2
        ]);
        $orderProduct->order()->associate($order);
        $orderProduct->product()->associate($variablePriceProduct);
        $orderProduct->save();

        // Check that the product was saved with the correct price in the pivot
        $this->assertNotNull($order->products->first(), 'Product was not saved to order');
        $this->assertEquals(800, $order->products->first()->pivot->price, 'Variable price not correctly stored in pivot');
        $this->assertEquals(1000, $order->products->first()->price, 'Product should retain its base price');

        // Calculate and verify total cost
        $calculatedCost = Util::calculateTotalOrderCostByModel($order);
        $expectedCost = 2 * 800; // Quantity 2 × price 800 = 1600

        $this->assertEquals($expectedCost, $calculatedCost, 'Order total with variable pricing not calculated correctly');
    }
}
