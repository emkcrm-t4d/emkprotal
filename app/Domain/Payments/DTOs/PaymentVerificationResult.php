<?php

declare(strict_types=1);

namespace App\Domain\Payments\DTOs;

readonly class PaymentVerificationResult
{
    public bool $isSuccessful;
    public string $transactionId;
    public ?string $errorCode;

    public function __construct(
        public bool $isVerified,
        public string $status,
        public string $reference,
        public array $evidencePayload = [],
        public ?string $rejectionReasonCode = null,
        public ?string $errorMessage = null
    ) {
        $this->isSuccessful = $this->isVerified;
        $this->transactionId = $this->reference;
        $this->errorCode = $this->rejectionReasonCode;
    }

    /**
     * Factory for successful verification result.
     */
    public static function success(
        string $reference,
        array $evidencePayload = [],
        string $status = 'verified'
    ): self {
        return new self(
            isVerified: true,
            status: $status,
            reference: $reference,
            evidencePayload: $evidencePayload,
            rejectionReasonCode: null,
            errorMessage: null
        );
    }

    /**
     * Factory for rejected verification result.
     */
    public static function rejected(
        string $reference,
        ?string $rejectionReasonCode = null,
        ?string $errorMessage = null,
        array $evidencePayload = []
    ): self {
        return new self(
            isVerified: false,
            status: 'rejected',
            reference: $reference,
            evidencePayload: $evidencePayload,
            rejectionReasonCode: $rejectionReasonCode,
            errorMessage: $errorMessage
        );
    }

    /**
     * Factory for failed verification result (alias for rejected).
     */
    public static function failure(
        string $reference,
        ?string $rejectionReasonCode = null,
        ?string $errorMessage = null,
        array $evidencePayload = []
    ): self {
        return self::rejected($reference, $rejectionReasonCode, $errorMessage, $evidencePayload);
    }
}
