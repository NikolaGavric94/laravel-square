<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Nikolag\Square\Utils\Constants;

class OrderProductModifierPivot extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_product_order_modifier';

    /**
     * The fillable attributes for the model.
     *
     * @var array
     */
    protected $fillable = [
        'order_product_id',
        'modifiable_id',
        'modifiable_type',
        'text',
        'quantity',
    ];

    /**
     * Return order connected with this product pivot.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function orderProduct()
    {
        return $this->belongsTo(OrderProductPivot::class);
    }

    /**
     * Return the modifier connected with this order product modifier pivot.
     *
     * @return MorphTo
     */
    public function modifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
