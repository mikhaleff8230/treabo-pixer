<?php

namespace App\Services\Tinkoff\Exceptions;

use RuntimeException;

class TinkoffException extends RuntimeException
{
    private array $errorData;

    public function __construct(string $message, array $errorData = [])
    {
        parent::__construct($message);
        $this->errorData = $errorData;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }
}