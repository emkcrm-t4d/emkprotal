<?php

declare(strict_types=1);

namespace App\Domain\Payments\Adapters;

use App\Domain\Payments\Contracts\PaymentAdapterInterface;
use App\Domain\Payments\Contracts\PaymentGatewayAdapterInterface;
use App\Domain\Payments\DTOs\PaymentInitiationRequest;
use App\Domain\Payments\DTOs\PaymentRedirectResponse;
use App\Domain\Payments\DTOs\PaymentVerificationRequest;
use App\Domain\Payments\DTOs\PaymentVerificationResult;
use App\Domain\Payments\DTOs\WebhookResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MockGatewayPaymentAdapter implements PaymentAdapterInterface, PaymentGatewayAdapterInterface
{
    public function __construct(
        protected array $config = []
    ) {}

    /**
     * Return the unique driver identifier.
     */
    public function getDriverName(): string
    {
        return 'mock';
    }

    /**
     * Indicate whether this adapter supports staff manual reconciliation.
     */
    public function supportsManualVerification(): bool
    {
        return false;
    }

    /**
     * Verify payment against simulated mock gateway records.
     */
    public function verifyPayment(PaymentVerificationRequest $request): PaymentVerificationResult
    {
        $txid = Str::upper(trim($request->paymentReference ?: $request->transactionId));

        $evidencePayload = [
            'stream' => 'payment',
            'status' => 'verified',
            'gateway' => 'mock',
            'transaction_id' => $txid,
            'amount_received_bdt' => $request->amountBdt,
            'expected_amount_bdt' => $request->amountBdt,
            'payment_method' => 'mock',
            'verified_at' => now()->toIso8601String(),
            'reviewer_user_id' => $request->reviewerUserId,
            'notes' => $request->notes ?? 'Automated mock gateway verification simulation.',
        ];

        return PaymentVerificationResult::success(
            reference: $txid,
            evidencePayload: $evidencePayload,
            status: 'verified'
        );
    }

    /**
     * Initiate an outbound payment session (simulated mock checkout redirect).
     */
    public function initiatePayment(PaymentInitiationRequest $request): PaymentRedirectResponse
    {
        $sessionId = 'MOCK-SESS-' . Str::random(16);
        $endpoint = $this->config['endpoint'] ?? 'https://payment.mock/checkout';
        $redirectUrl = $endpoint . '?session=' . $sessionId . '&invoice=' . $request->invoiceId;

        return new PaymentRedirectResponse(
            redirectUrl: $redirectUrl,
            gatewaySessionId: $sessionId,
            parameters: [
                'invoice_id' => $request->invoiceId,
                'member_id' => $request->memberId,
                'amount_bdt' => $request->amountBdt,
                'customer_name' => $request->customerName,
                'customer_email' => $request->customerEmail,
                'customer_phone' => $request->customerPhone,
                'callback_url' => $request->callbackUrl,
                'cancel_url' => $request->cancelUrl,
                'metadata' => $request->metadata,
            ]
        );
    }

    /**
     * Process an asynchronous gateway callback / Instant Payment Notification (IPN).
     * Validates simulated signature (X-Mock-Signature header or signature parameter).
     */
    public function handleWebhook(Request $request): WebhookResult
    {
        $secret = (string) ($this->config['secret'] ?? 'mock-secret-key');
        $signature = (string) ($request->header('X-Mock-Signature') ?? $request->input('signature', ''));

        if ($signature === '') {
            return WebhookResult::failure(
                errorMessage: 'Missing webhook digital signature header [X-Mock-Signature].',
                rawPayload: $request->all()
            );
        }

        $rawBody = (string) $request->getContent();
        $expectedSignatureRaw = hash_hmac('sha256', $rawBody, $secret);
        $expectedSignaturePayload = hash_hmac('sha256', (string) json_encode($request->except('signature')), $secret);
        $concatData = (string) ($request->input('transaction_id') . ':' . $request->input('amount_bdt', $request->input('amount')));
        $expectedSignatureConcat = hash_hmac('sha256', $concatData, $secret);

        $isValidSignature = hash_equals($expectedSignatureRaw, $signature)
            || hash_equals($expectedSignaturePayload, $signature)
            || hash_equals($expectedSignatureConcat, $signature);

        if (!$isValidSignature) {
            return WebhookResult::failure(
                errorMessage: 'Invalid webhook digital signature.',
                rawPayload: $request->all()
            );
        }

        $trxId = (string) ($request->input('transaction_id') ?? $request->input('trx_id') ?? 'MOCK-TRX-' . Str::upper(Str::random(10)));
        $paymentRef = (string) ($request->input('payment_reference') ?? $request->input('reference') ?? 'REF-' . Str::upper(Str::random(10)));
        $amountBdt = (float) ($request->input('amount_bdt') ?? $request->input('amount') ?? 0.0);
        $status = (string) ($request->input('status', 'paid'));

        if ($status === 'paid') {
            return WebhookResult::success(
                transactionId: $trxId,
                paymentReference: $paymentRef,
                amountBdt: $amountBdt,
                rawPayload: $request->all(),
                status: 'paid'
            );
        }

        return new WebhookResult(
            isHandled: true,
            transactionId: $trxId,
            paymentReference: $paymentRef,
            amountBdt: $amountBdt,
            status: $status,
            rawPayload: $request->all(),
            errorMessage: $status === 'failed' ? ($request->input('error_message') ?? 'Payment failed at gateway') : null
        );
    }
}
