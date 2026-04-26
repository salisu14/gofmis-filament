<?php

namespace App\Exceptions\Imprest;

use Exception;

class TransactionNotVoidableException extends Exception
{
    public function __construct(string $message = 'Transaction cannot be voided')
    {
        parent::__construct($message);
    }
}
