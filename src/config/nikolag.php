<?php

return [
	'square' => [
		'application_id' => env('SQUARE_APPLICATION_ID'),
		'access_token' => env('SQUARE_TOKEN')
	],
	'user' => [
		'namespace' => env('SQUARE_USER_NAMESPACE'),
		'identifier' => env('SQUARE_USER_IDENTIFIER', 'id')
	],
	'order' => [
		'namespace' => env('SQUARE_ORDER_NAMESPACE'),
		'identifier' => env('SQUARE_ORDER_IDENTIFIER', 'id')
	]
];