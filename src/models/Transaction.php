<?php

namespace Nikolag\Square\Models;

use Nikolag\Square\Utils\Constants;
use Nikolag\Core\Models\Transaction as CoreTransaction;

class Transaction extends CoreTransaction
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'payment_service_type' => 'square'
    ];

    /**
     * Seller from this transaction.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    function merchant()
    {
        return $this->belongsTo(config('nikolag.connections.square.user.namespace'), config('nikolag.connections.square.user.identifier'), 'merchant_id');
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
        return $this->belongsTo(config('nikolag.connections.square.order.namespace'), Constants::ORDER_IDENTIFIER, Constants::TRANSACTION_IDENTIFIER);
    }
}