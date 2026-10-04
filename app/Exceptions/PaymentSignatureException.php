<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentSignatureException extends RuntimeException
{
    public function __construct(string $message = 'Invalid payment signature.')
    {
        parent::__construct($message);
    }
}
