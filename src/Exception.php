<?php
namespace Nikolag\Square;

use Illuminate\Queue\SerializesModels;
use Nikolag\Core\Exceptions\Exception as BaseException;

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
	public function __construct($message = null, $code = 0, Exception $previous = null)
	{
		//Parent
		parent::__construct($message, $code, $previous);
	}
}