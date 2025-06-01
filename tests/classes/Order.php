<?php

namespace Nikolag\Square\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\Traits\HasFulfillments;
use Nikolag\Square\Traits\HasProducts;
use Nikolag\Square\Traits\HasReturns;

class Order extends Model
{
    use HasProducts;
    use HasFulfillments;
    use HasReturns;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nikolag_orders';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payment_service_id',
    ];
}
