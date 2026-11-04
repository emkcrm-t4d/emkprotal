<?php

declare(strict_types=1);

namespace App\Domain\IdCards\Exceptions;

use RuntimeException;

final class InvalidCardStateTransitionException extends RuntimeException
{
    public function __construct(string $message = 'Invalid ID card state transition.', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return $this->getCode() ?: 422;
    }
}
