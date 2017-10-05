<?php

namespace Nikolag\Square\Utils;

use Nikolag\Core\Utils\Constants as CoreConstants;

class Constants extends CoreConstants {
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