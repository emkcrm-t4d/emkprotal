<?php

declare(strict_types=1);

namespace App\Domain\Payments\DTOs;

readonly class PaymentVerificationRequest
{
    public string $transactionId;

    public function __construct(
        public int $leadId,
        public string $paymentReference,
        public float $amountBdt,
        public int $reviewerUserId,
        public ?string $notes = null,
        public array $metadata = []
    ) {
        $this->transactionId = $this->paymentReference;
    }

    /**
     * Get transaction ID alias for paymentReference.
     */
    public function getTransactionId(): string
    {
        return $this->paymentReference;
    }
}
