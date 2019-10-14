<?php

namespace Nikolag\Square\Utils;

use Nikolag\Core\Utils\Constants as CoreConstants;

class Constants extends CoreConstants
{
    //Transaction info
    const TRANSACTION_NAMESPACE = 'Nikolag\Square\Models\Transaction';
    const TRANSACTION_IDENTIFIER = 'id';
    //Transaction statuses
    const TRANSACTION_STATUS_OPENED = 'PENDING';
    const TRANSACTION_STATUS_PASSED = 'PAID';
    const TRANSACTION_STATUS_FAILED = 'FAILED';
    //Customer info
    const CUSTOMER_NAMESPACE = 'Nikolag\Square\Models\Customer';
    const CUSTOMER_IDENTIFIER = 'id';
    //Product info
    const ORDER_PRODUCT_NAMESPACE = 'Nikolag\Square\Models\OrderProductPivot';
    const PRODUCT_NAMESPACE = 'Nikolag\Square\Models\Product';
    const PRODUCT_IDENTIFIER = 'id';
    //Discount info
    const DISCOUNT_NAMESPACE = 'Nikolag\Square\Models\Discount';
    const DISCOUNT_IDENTIFIER = 'id';
    //Tax info
    const TAX_NAMESPACE = 'Nikolag\Square\Models\Tax';
    const TAX_IDENTIFIER = 'id';

    //Exceptions
    //INVALID_REQUEST_ERROR
    const INVALID_REQUEST_ERROR = 'INVALID_REQUEST_ERROR';
    const INVALID_VALUE = 'INVALID_VALUE';
    const NOT_FOUND = 'NOT_FOUND';
    //PAYMENT_METHOD_ERROR
    const BAD_REQUEST = 'BAD_REQUEST';
    const PAYMENT_METHOD_ERROR = 'PAYMENT_METHOD_ERROR';
    const INVALID_EXPIRATION = 'INVALID_EXPIRATION';
    const VERIFY_POSTAL_CODE = 'ADDRESS_VERIFICATION_FAILURE';
    const VERIFY_CVV = 'CVV_FAILURE';
}
