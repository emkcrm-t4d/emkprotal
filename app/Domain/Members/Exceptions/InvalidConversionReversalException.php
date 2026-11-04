<?php

declare(strict_types=1);

namespace App\Domain\Members\Exceptions;

use RuntimeException;

final class InvalidConversionReversalException extends RuntimeException
{
    /** @var array<string, string> */
    private array $reasons;

    /**
     * @param array<string, string> $reasons
     */
    public function __construct(string $message, array $reasons = [], int $code = 422)
    {
        parent::__construct($message, $code);
        $this->reasons = $reasons;
    }

    /**
     * Get list of unsatisfied reversal conditions.
     *
     * @return array<string, string>
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->getCode() ?: 422;
    }
}
