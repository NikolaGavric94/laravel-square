<?php

namespace Nikolag\Square\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Schema;
use Nikolag\Core\Exceptions\Exception;
use Nikolag\Square\Facades\Square;
use Nikolag\Square\Models\Discount;
use Nikolag\Square\Models\Product;
use Nikolag\Square\Models\Tax;
use Nikolag\Square\Models\Transaction;
use Nikolag\Square\Utils\Constants;

trait HasProducts
{
    /**
     * Charge an order.
     *
     * @param  float  $amount
     * @param  string  $nonce
     * @param  string  $location_id
     * @param  mixed  $merchant
     * @param  array  $options
     * @param  mixed|null  $customer
     * @param  string  $currency
     * @return Transaction
     *
     * @throws Exception
     */
    public function charge(float $amount, string $nonce, string $location_id, mixed $merchant, array $options = [], mixed $customer = null, string $currency = 'USD'): Transaction
    {
        return Square::setOrder($this, $location_id)->setMerchant($merchant)->setCustomer($customer)->charge(
            array_merge(['amount' => $amount, 'source_id' => $nonce, 'location_id' => $location_id, 'currency' => $currency], $options)
        );
    }

    /**
     * Check existence of an attribute in model.
     *
     * @param  string  $attribute
     * @return bool
     */
    public function hasColumn(string $attribute): bool
    {
        return Schema::hasColumn($this->table, $attribute);
    }

    /**
     * Does an order have a discount.
     *
     * @param  mixed  $discount
     * @return bool
     */
    public function hasDiscount(mixed $discount): bool
    {
        $val = is_array($discount) ? array_key_exists('id', $discount) ? Discount::find($discount['id']) : $discount : $discount;

        return $this->discounts()->get()->contains($val);
    }

    /**
     * Does an order have a tax.
     *
     * @param  mixed  $tax
     * @return bool
     */
    public function hasTax(mixed $tax): bool
    {
        $val = is_array($tax) ? array_key_exists('id', $tax) ? Tax::find($tax['id']) : $tax : $tax;

        return $this->taxes()->get()->contains($val);
    }

    /**
     * Does an order have a product.
     *
     * @param  mixed  $product
     * @return bool
     */
    public function hasProduct(mixed $product): bool
    {
        $val = is_array($product) ? array_key_exists('id', $product) ? Product::find($product['id']) : $product : $product;

        return $this->products()->get()->contains($val);
    }

    /**
     * Attach a product to the order with automatic price inclusion.
     *
     * @param mixed $product
     * @param array $attributes
     * @return void
     */
    public function attachProduct($product, array $attributes = [])
    {
        $productModel = $product instanceof Product ? $product : Product::find($product);

        // Merge the product's current price into the pivot attributes
        $pivotData = array_merge($attributes, [
            'price_money_amount' => $productModel->price
        ]);

        $this->products()->attach($product, $pivotData);
    }

    /**
     * Return a list of products which are included in this order.
     *
     * @return BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Constants::PRODUCT_NAMESPACE, 'nikolag_product_order', 'order_id', 'product_id')->using(Constants::ORDER_PRODUCT_NAMESPACE)->withPivot('quantity', 'id', 'square_uid', 'price_money_amount');
    }

    /**
     * Return a list of taxes which are in included in this order.
     *
     * @return MorphToMany
     */
    public function taxes(): MorphToMany
    {
        return $this->morphToMany(Constants::TAX_NAMESPACE, 'featurable', 'nikolag_deductibles', 'featurable_id', 'deductible_id')->where('deductible_type', Constants::TAX_NAMESPACE)->distinct()->withPivot('scope');
    }

    /**
     * Return a list of discounts which are in included in this order.
     *
     * @return MorphToMany
     */
    public function discounts(): MorphToMany
    {
        return $this->morphToMany(Constants::DISCOUNT_NAMESPACE, 'featurable', 'nikolag_deductibles', 'featurable_id', 'deductible_id')->where('deductible_type', Constants::DISCOUNT_NAMESPACE)->distinct()->withPivot('scope');
    }
}
