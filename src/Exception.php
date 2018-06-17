<?php

namespace Nikolag\Square;

use Exception as PhpException;
use Illuminate\Queue\SerializesModels;
use Nikolag\Core\Exceptions\Exception as BaseException;

class Exception extends BaseException
{
    use SerializesModels;

    /**
     * Constructor.
     *
     * @param mixed $message
     * @param mixed $code
     * @param PhpException $previous
     */
    public function __construct($message = null, $code = 0, PhpException $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
