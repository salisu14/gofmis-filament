<?php

namespace App\Exceptions\Imprest;

use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(string $message = 'Insufficient funds in imprest account')
    {
        parent::__construct($message);
    }
}
