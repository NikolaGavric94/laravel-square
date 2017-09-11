<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Square Configuration
    |--------------------------------------------------------------------------
    |
    | The square configuration determines the default application_id
    | and square token when doing any of the calls to square. These values will
    | be used when there is no merchant provided as a seller. You have to change
    | these values.
    |
    */
	'square' => [
		'application_id' => env('SQUARE_APPLICATION_ID'),
		'access_token' => env('SQUARE_TOKEN')
	],
	/*
    |--------------------------------------------------------------------------
    | Square Merchant Configuration
    |--------------------------------------------------------------------------
    |
    | The square merchant configuration determines the default namespace for
    | merchant model and it's identifier which will be used in various
    | relationships when retrieving models. You are encouraged to change these
    | values to better reflect your application.
    |
    */
	'user' => [
		'namespace' => env('SQUARE_USER_NAMESPACE', '\App\User'),
		'identifier' => env('SQUARE_USER_IDENTIFIER', 'id')
	],
	/*
    |--------------------------------------------------------------------------
    | Square Order Configuration
    |--------------------------------------------------------------------------
    |
	| The square order configuration determines the default namespace for
	| order model and it's identifier which CAN be used when charging a customer.
	| You can relate that model to a certain transaction. You are encouraged to
	| change these values to better reflect your application.
    |
    */
	'order' => [
		'namespace' => env('SQUARE_ORDER_NAMESPACE', '\App\Order'),
		'identifier' => env('SQUARE_ORDER_IDENTIFIER', 'id')
	]
];