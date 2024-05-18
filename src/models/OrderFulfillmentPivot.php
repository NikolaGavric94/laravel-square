<?php

namespace Nikolag\Square\Models;

use DateTimeInterface;
use Nikolag\Square\Utils\Constants;
use Illuminate\Database\Eloquent\Model;

class OrderFulfillmentPivot extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_fulfillment_order';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
    ];
}
