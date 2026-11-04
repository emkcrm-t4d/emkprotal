<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Exceptions;

use RuntimeException;

final class DuplicatePaymentReferenceException extends RuntimeException
{
    public function __construct(string $message = 'Duplicate payment reference detected.', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return $this->getCode() ?: 422;
    }
}
