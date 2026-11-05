<?php

declare(strict_types=1);

namespace App\Domain\Payments\DTOs;

readonly class WebhookResult
{
    public function __construct(
        public bool $isHandled,
        public string $transactionId,
        public string $paymentReference,
        public float $amountBdt,
        public string $status,
        public array $rawPayload,
        public ?string $errorMessage = null
    ) {}

    public static function success(
        string $transactionId,
        string $paymentReference,
        float $amountBdt,
        array $rawPayload,
        string $status = 'paid'
    ): self {
        return new self(
            isHandled: true,
            transactionId: $transactionId,
            paymentReference: $paymentReference,
            amountBdt: $amountBdt,
            status: $status,
            rawPayload: $rawPayload,
            errorMessage: null
        );
    }

    public static function failure(
        string $errorMessage,
        array $rawPayload = [],
        string $transactionId = '',
        string $paymentReference = '',
        float $amountBdt = 0.0,
        string $status = 'failed'
    ): self {
        return new self(
            isHandled: false,
            transactionId: $transactionId,
            paymentReference: $paymentReference,
            amountBdt: $amountBdt,
            status: $status,
            rawPayload: $rawPayload,
            errorMessage: $errorMessage
        );
    }
}
