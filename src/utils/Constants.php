<?php

namespace Nikolag\Square\Utils;

class Constants {
	//Transaction info
	const TRANSACTION_NAMESPACE 	= 'Nikolag\Square\Models\Transaction';
	const TRANSACTION_IDENTIFIER 	= 'id';
	//Transaction statuses
	const TRANSACTION_STATUS_OPENED = 'PENDING';
	const TRANSACTION_STATUS_PASSED = 'PAID';
	const TRANSACTION_STATUS_FAILED = 'FAILED';
	//Customer info
	const CUSTOMER_NAMESPACE 		= 'Nikolag\Square\Models\Customer';
	const CUSTOMER_IDENTIFIER 		= 'id';
	//Exceptions
	//INVALID_REQUEST_ERROR
	const INVALID_REQUEST_ERROR 	= 'INVALID_REQUEST_ERROR';
	const NOT_FOUND 				= 'NOT_FOUND';
	//PAYMENT_METHOD_ERROR
	const PAYMENT_METHOD_ERROR 		= 'PAYMENT_METHOD_ERROR';
	const INVALID_EXPIRATION 		= 'INVALID_EXPIRATION';
	const VERIFY_POSTAL_CODE 		= 'VERIFY_AVS_FAILURE';
	const VERIFY_CVV 				= 'VERIFY_CVV_FAILURE';
}