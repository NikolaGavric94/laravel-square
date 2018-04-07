<?php

namespace Nikolag\Square\Models;

use Nikolag\Core\Models\Transaction as CoreTransaction;
use Nikolag\Square\Utils\Constants;

class Transaction extends CoreTransaction
{
    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'payment_service_type' => 'square',
    ];

    /**
     * Seller from this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant()
    {
        return $this->belongsTo(config('nikolag.connections.square.user.namespace'), 'merchant_id', config('nikolag.connections.square.user.identifier'));
    }

    /**
     * Buyer from this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Constants::CUSTOMER_NAMESPACE, 'customer_id', Constants::CUSTOMER_IDENTIFIER);
    }

    /**
     * Order from this transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(config('nikolag.connections.square.order.namespace'), 'order_id', Constants::TRANSACTION_IDENTIFIER);
    }
}
