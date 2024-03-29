<?php

namespace Nikolag\Square\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Nikolag\Square\Exceptions\InvalidSquareOrderException;
use Nikolag\Square\Exceptions\MissingPropertyException;
use Nikolag\Square\Models\Fulfillment;
use Nikolag\Square\Models\PickupDetails;
use Nikolag\Square\Models\OrderFulfillmentPivot;
use Nikolag\Square\Utils\Constants;
use stdClass;

class FulfillmentBuilder
{
    /**
     * @var DiscountBuilder
     */
    private DiscountBuilder $discountBuilder;
    /**
     * @var TaxesBuilder
     */
    private TaxesBuilder $taxesBuilder;

    public function __construct()
    {
        $this->discountBuilder = new DiscountBuilder();
        $this->taxesBuilder    = new TaxesBuilder();
    }

    /**
     * Add a product to the order from model as source.
     *
     * @param  Model  $order
     * @param  Model  $fulfillment
     * @param  int  $quantity
     * @return Fulfillment|stdClass
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function createFulfillmentFromModel(Model $order, Model $fulfillment): Fulfillment|stdClass
    {
        try {
            $productCopy = $this->createProductFromModel($product, $order, $quantity);
            // Create discounts Collection
            $productCopy->discounts = collect([]);
            // //Discounts
            if ($product->discounts && $product->discounts->isNotEmpty()) {
                $productCopy->discounts = $this->discountBuilder->createDiscounts($product->discounts->toArray(), Constants::DEDUCTIBLE_SCOPE_PRODUCT, $productCopy->product);
            }
            // Create taxes Collection
            $productCopy->taxes = collect([]);
            //Taxes
            if ($product->taxes && $product->taxes->isNotEmpty()) {
                $productCopy->taxes = $this->taxesBuilder->createTaxes($product->taxes->toArray(), Constants::DEDUCTIBLE_SCOPE_PRODUCT, $productCopy->product);
            }

            return $productCopy;
        } catch (MissingPropertyException $e) {
            throw new MissingPropertyException('Required field is missing', 500, $e);
        }
    }

    /**
     * Add a fulfillment to the order from array as source.
     *
     * @param  Model  $order
     * @param  array  $fulfillment
     * @param  string  $type
     * @return Fulfillment|stdClass
     *
     * @throws InvalidSquareOrderException
     * @throws MissingPropertyException
     */
    public function createFulfillmentFromArray(
        Model $order,
        array $fulfillment,
        string $type
    ): Model|stdClass {
        if ($type == Constants::FULFILLMENT_TYPE_DELIVERY) {
            $fulfillmentCopy = $this->createDeliveryFulfillmentFromArray($fulfillment, $order);
        } elseif ($type == Constants::FULFILLMENT_TYPE_PICKUP) {
            $fulfillmentCopy = $this->createPickupFulfillmentFromArray($fulfillment, $order);
        } elseif ($type == Constants::FULFILLMENT_TYPE_SHIPMENT) {
            $fulfillmentCopy = $this->createShipmentFulfillmentFromArray($fulfillment, $order);
        } else {
            throw new InvalidSquareOrderException('Invalid fulfillment type', 500);
        }

        return $fulfillmentCopy;
    }

    /**
     * Create pickup fulfillment from array.
     *
     * @param  array  $fulfillment
     * @param  Model  $order
     * @return PickupDetails|stdClass
     *
     * @throws MissingPropertyException
     */
    public function createPickupFulfillmentFromArray(array $fulfillment, Model $order): PickupDetails|stdClass
    {
        $fulfillmentObj = new stdClass();
        // If fulfillment doesn't have a state in the array
        // throw new exception because every fulfillment should have a state
        if (! Arr::has($fulfillment, 'state') || $fulfillment['state'] == null) {
            throw new MissingPropertyException('"state" property for object Fulfillment is missing', 500);
        }

        // Check if order is present and if already has this fulfillment
        // or if fulfillment doesn't have property $id then create new fulfillment object
        if (
            (!$order->hasFulfillment())
            || ! Arr::has($fulfillment, 'id')
        ) {
            // Get the details
            $pickupData = Arr::get($fulfillment, 'pickup_details');
            if (!$pickupData) {
                throw new MissingPropertyException('pickup_details property for object Fulfillment is missing', 500);
            }

            $tempFulfillment  = new PickupDetails($pickupData);
            $fulfillmentPivot = new OrderFulfillmentPivot($pickupData);
        } else {
            $tempFulfillment  = PickupDetails::find($fulfillment['id']);
            $fulfillmentPivot = OrderFulfillmentPivot::where('order_id', $order->id)
                ->where('fulfillment_id', $tempFulfillment->id)
                ->first();
            if (! $fulfillmentPivot) {
                $fulfillmentPivot = new OrderFulfillmentPivot($fulfillment);
            }
        }

        $fulfillmentObj = $tempFulfillment;
        $fulfillmentObj->pivot = $fulfillmentPivot;

        return $fulfillmentObj;
    }
}
