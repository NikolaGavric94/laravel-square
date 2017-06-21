<?php

namespace Nikolag\Square;

use Nikolag\Square\Contracts\SquareContract;
use Nikolag\Square\SquareCustomer;
use SquareConnect\Api\CustomersApi;
use SquareConnect\Api\LocationsApi;
use SquareConnect\Api\TransactionsApi;
use SquareConnect\Configuration;

class SquareConfig {
	private $config;
	public $locationsAPI;
	public $customersAPI;
	public $transactionsAPI;

	function __construct($config) {
		$this->config = config('services.square');
		$this->setAccessToken($this->config->access_token);
		$this->locationsAPI = new LocationsApi();
		$this->customersAPI = new CustomersApi();
		$this->transactionsAPI = new TransactionsApi();
	}

	function setAccessToken(string $accessToken) {
		Configuration::getDefaultConfiguration()->setAccessToken($accessToken);
	}

	function locationsAPI() {
		return $this->locationsAPI;
	}

	function customersAPI() {
		return $this->customersAPI;
	}

	function transactionsAPI() {
		return $this->transactionsAPI;
	}
}