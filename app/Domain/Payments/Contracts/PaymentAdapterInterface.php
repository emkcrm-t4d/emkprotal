<?php

declare(strict_types=1);

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DTOs\PaymentVerificationRequest;
use App\Domain\Payments\DTOs\PaymentVerificationResult;

interface PaymentAdapterInterface
{
    /**
     * Return the unique string identifier for this payment driver.
     * E.g. 'bkash_manual', 'sslcommerz', 'mock'.
     */
    public function getDriverName(): string;

    /**
     * Indicate whether this adapter supports staff manual reconciliation.
     * Returns true for 'bkash_manual'; false for automated online gateways.
     */
    public function supportsManualVerification(): bool;

    /**
     * Verify a payment transaction (synchronous verification).
     */
    public function verifyPayment(PaymentVerificationRequest $request): PaymentVerificationResult;
}
