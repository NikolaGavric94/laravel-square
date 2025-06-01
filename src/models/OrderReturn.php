<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Square\Models\OrderReturn as SquareOrderReturn;

class OrderReturn extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_order_returns';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uid',
        'source_order_id',
        'data',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    //
    // Relationships
    //

    /**
     * Get the original order associated with this return.
     *
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(config('nikolag.connections.square.order.namespace'), 'source_order_id', config('nikolag.connections.square.order.service_identifier'));
    }

    /**
     * Get the square order return data
     *
     * @return Attribute
     */
    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => is_null($value) ? new SquareOrderReturn() : new SquareOrderReturn(json_decode($value, true)),
            set: fn (SquareOrderReturn $value) => json_encode($value)
        );

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
