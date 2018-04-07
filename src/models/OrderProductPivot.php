<?php

namespace Nikolag\Square\Models;

use Nikolag\Core\Models\OrderProductPivot as IntermediateTable;
use Nikolag\Square\Utils\Constants;

class OrderProductPivot extends IntermediateTable
{
    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return static::UPDATED_AT;
    }

    /**
     * Does intermediate table has discount.
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
     * Does intermediate table has tax.
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
     * Does intermediate table has product.
     *
     * @param mixed $product
     *
     * @return bool
     */
    public function hasProduct($product)
    {
        $val = is_array($product) ? array_key_exists('id', $product) ? Product::find($product['id']) : $product : $product;

        return $this->product === $val;
    }

    /**
     * Return order connected with this product pivot.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(config('nikolag.connections.square.order.namespace'), 'order_id', config('nikolag.connections.square.order.identifier'));
    }

    /**
     * Return product connected with this product pivot.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Constants::PRODUCT_NAMESPACE, 'product_id', 'id');
    }

    /**
     * Return a list of taxes which are in included in this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function taxes()
    {
        return $this->morphToMany(Constants::TAX_NAMESPACE, 'featurable', 'nikolag_deductibles', 'featurable_id', 'deductible_id')->where('deductible_type', Constants::TAX_NAMESPACE);
    }

    /**
     * Return a list of discounts which are in included in this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function discounts()
    {
        return $this->morphToMany(Constants::DISCOUNT_NAMESPACE, 'featurable', 'nikolag_deductibles', 'featurable_id', 'deductible_id')->where('deductible_type', Constants::DISCOUNT_NAMESPACE);
    }
}
