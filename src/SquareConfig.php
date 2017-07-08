<?php

namespace Nikolag\Square;

use Nikolag\Square\Contracts\SquareContract;
use Nikolag\Square\Exceptions\InvalidConfigurationException;
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

	function __construct()
	{
		$this->config = config('nikolag');
		if(empty($this->config) || !isset($this->config)) {
			throw new InvalidConfigurationException(
				"Missing required configuration for nikolag\laravel-square.", 500
			);
		}
		$this->setAccessToken($this->config['square']['access_token']);
		$this->locationsAPI = new LocationsApi();
		$this->customersAPI = new CustomersApi();
		$this->transactionsAPI = new TransactionsApi();
	}

	/**
	 * Access token for square.
	 * 
	 * @param string $accessToken 
	 * @return void
	 */
	public function setAccessToken(string $accessToken)
	{
		Configuration::getDefaultConfiguration()->setAccessToken($accessToken);
	}

	/**
	 * Api for locations.
	 * 
	 * @return \SquareConnect\Api\LocationsApi
	 */
	public function locationsAPI()
	{
		return $this->locationsAPI;
	}

	/**
	 * Api for customers.
	 * 
	 * @return \SquareConnect\Api\CustomersApi
	 */
	public function customersAPI()
	{
		return $this->customersAPI;
	}

	/**
	 * Api for transactions.
	 * 
	 * @return \SquareConnect\Api\TransactionsApi
	 */
	public function transactionsAPI()
	{
		return $this->transactionsAPI;
	}
}