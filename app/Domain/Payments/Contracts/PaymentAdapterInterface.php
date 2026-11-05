<?php

declare(strict_types=1);

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DTOs\PaymentInitiationRequest;
use App\Domain\Payments\DTOs\PaymentRedirectResponse;
use App\Domain\Payments\DTOs\PaymentVerificationRequest;
use App\Domain\Payments\DTOs\PaymentVerificationResult;
use App\Domain\Payments\DTOs\WebhookResult;
use Illuminate\Http\Request;

interface PaymentAdapterInterface
{
    /**
     * Return the unique string identifier for this payment driver.
     * E.g. 'bkash_manual', 'sslcommerz', 'bkash_gateway', 'mock'.
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

    /**
     * Initiate an outbound payment session (online checkout redirect).
     */
    public function initiatePayment(PaymentInitiationRequest $request): PaymentRedirectResponse;

    /**
     * Process an asynchronous gateway callback / Instant Payment Notification (IPN).
     */
    public function handleWebhook(Request $request): WebhookResult;
}
