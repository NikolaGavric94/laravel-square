<?php
namespace Nikolag\Square;

use Illuminate\Queue\SerializesModels;
use Nikolag\Core\Exceptions\Exception as BaseException;
use \Exception as PhpException;

class Exception extends BaseException
{
    use SerializesModels;

    /**
     * Constructor.
     *
     * @param mixed $message
     * @param mixed $code
     * @param BaseException $previous
     * 
     * @return void
     */
    public function __construct($message = null, $code = 0, PhpException $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
