<?php
namespace Nikolag\Square;

use Illuminate\Queue\SerializesModels;
use \Exception as BaseException;

class Exception extends BaseException {
	use SerializesModels;

	/**
	 * Constructor.
	 * 
	 * @param type|null $message 
	 * @param type $code 
	 * @param BaseException|null $previous 
	 * @return void
	 */
	public function __construct($message = null, $code = 0, BaseException $previous = null)
	{
		//Parent
		parent::__construct($message, $code, $previous);
	}
}