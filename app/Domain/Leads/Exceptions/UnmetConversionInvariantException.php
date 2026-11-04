<?php

declare(strict_types=1);

namespace App\Domain\Leads\Exceptions;

use RuntimeException;

final class UnmetConversionInvariantException extends RuntimeException
{
    /** @var array<string, string> */
    private array $unmetConditions;

    /**
     * @param array<string, string> $unmetConditions
     */
    public function __construct(string $message, array $unmetConditions = [], int $code = 422)
    {
        parent::__construct($message, $code);
        $this->unmetConditions = $unmetConditions;
    }

    /**
     * Get list of unsatisfied conversion conditions.
     *
     * @return array<string, string>
     */
    public function getUnmetConditions(): array
    {
        return $this->unmetConditions;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->getCode() ?: 422;
    }
}
