<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(string $message = 'Insufficient Balance! Please fund your wallet to continue the transaction.')
    {
        parent::__construct($message);
    }
}
