<?php

declare(strict_types=1);

namespace Switch\Session\Exception;

use RuntimeException;

class TokenMismatchException extends RuntimeException
{
    private int $statusCode = 419;

    public function __construct(string $message = 'CSRF token mismatch. The page session has expired. Please refresh and try again.')
    {
        parent::__construct($message, 419);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
