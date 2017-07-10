<?php

namespace Nikolag\Square\Models;

use Illuminate\Database\Eloquent\Model;
use Nikolag\Square\SquareCustomer;
use Nikolag\Square\Utils\Constants;

class Transaction extends Model
{
	/**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "nikolag_transactions";

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
    	'status', 'amount'
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
        'reference_id',
        'reference_type'
    ];

    /**
     * Seller from this transaction.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    function merchant()
    {
        return $this->belongsTo(config('nikolag.user.namespace'), config('nikolag.user.identifier'), 'merchant_id');
    }

    /**
     * Buyer from this transaction.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    function customer()
    {
        return $this->belongsTo(Constants::CUSTOMER_NAMESPACE, Constants::CUSTOMER_IDENTIFIER, 'customer_id');
    }

    /**
     * Description
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    function order()
    {
        return $this->belongsTo(config('nikolag.order_namespace'), Constants::ORDER_IDENTIFIER, Constants::TRANSACTION_IDENTIFIER);
    }
}