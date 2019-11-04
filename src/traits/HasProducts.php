<?php

namespace Nikolag\Square\Traits;

use Illuminate\Support\Facades\Schema;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Utils\Constants;

trait HasProducts
{
    /**
     * Charge an order.
     *
     * @param float $amount
     * @param string $nonce
     * @param string $location_id
     * @param mixed $merchant
     * @param array $options
     * @param mixed $customer
     * @param string $currency
     *
     * @return \Nikolag\Square\Models\Transaction
     */
    public function charge(float $amount, string $nonce, string $location_id, $merchant, array $options = [], $customer = null, string $currency = 'USD')
    {
        return Square::setOrder($this, $location_id)->setMerchant($merchant)->setCustomer($customer)->charge(
            array_merge(['amount' => $amount, 'source_id' => $nonce, 'location_id' => $location_id, 'currency' => $currency], $options)
        );
    }

    /**
     * Check existence of an attribute in model.
     *
     * @param string $attribute
     *
     * @return bool
     */
    public function hasAttribute(string $attribute)
    {
        return Schema::hasColumn($this->table, $attribute);
    }

    /**
     * Does an order have a discount.
     *
     * @param mixed $discount
     *
     * @return bool
     */
    public function hasDiscount($discount)
    {
        $val = is_array($discount) ? array_key_exists('id', $discount) ? Discount::find($discount['id']) : $discount : $discount;

        return $this->discounts()->get()->contains($val);
    }

    /**
     * Does an order have a tax.
     *
     * @param mixed $tax
     *
     * @return bool
     */
    public function hasTax($tax)
    {
        $val = is_array($tax) ? array_key_exists('id', $tax) ? Tax::find($tax['id']) : $tax : $tax;

        return $this->taxes()->get()->contains($val);
    }

    /**
     * Does an order have a product.
     *
     * @param mixed $product
     *
     * @return bool
     */
    public function hasProduct($product)
    {
        $val = is_array($product) ? array_key_exists('id', $product) ? Product::find($product['id']) : $product : $product;

        return $this->products()->get()->contains($val);
    }

    /**
     * Return a list of products which are included in this order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products()
    {
        return $this->belongsToMany(Constants::PRODUCT_NAMESPACE, 'nikolag_product_order', 'order_id', 'product_id')->using(Constants::ORDER_PRODUCT_NAMESPACE)->withPivot('quantity', 'id');
    }

    /**
     * Return a list of taxes which are in included in this order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function taxes()
    {
        return $this->morphToMany(Constants::TAX_NAMESPACE, 'featurable', 'nikolag_deductibles', 'featurable_id', 'deductible_id')->where('deductible_type', Constants::TAX_NAMESPACE)->distinct();
    }

    /**
     * Return a list of discounts which are in included in this order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function discounts()
    {
        return $this->morphToMany(Constants::DISCOUNT_NAMESPACE, 'featurable', 'nikolag_deductibles', 'featurable_id', 'deductible_id')->where('deductible_type', Constants::DISCOUNT_NAMESPACE)->distinct();
    }
}
