<?php

declare(strict_types=1);

namespace App\Domain\Payments\Adapters;

use App\Domain\Payments\Contracts\PaymentAdapterInterface;
use App\Domain\Payments\DTOs\PaymentInitiationRequest;
use App\Domain\Payments\DTOs\PaymentRedirectResponse;
use App\Domain\Payments\DTOs\PaymentVerificationRequest;
use App\Domain\Payments\DTOs\PaymentVerificationResult;
use App\Domain\Payments\DTOs\WebhookResult;
use App\Domain\Payments\Exceptions\UnsupportedPaymentOperationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualBkashPaymentAdapter implements PaymentAdapterInterface
{
    public const TXID_REGEX = '/^[A-Z0-9]{10}$/';

    /**
     * Clean and extract a 10-character uppercase bKash Transaction ID from any raw input or SMS snippet.
     * Automatically strips "TrxID", "TRX ID", "TxID" prefixes and whitespace.
     */
    public static function extractTxid(string $raw): string
    {
        $text = trim($raw);

        if ($text === '') {
            return '';
        }

        // 1. If the raw string is already exactly 10 alphanumeric characters (e.g. 'TXIDDUP001', 'BLK7X9Q12M'),
        // it is already a direct transaction ID. Do not strip any substring.
        if (preg_match('/^[A-Za-z0-9]{10}$/', $text)) {
            return strtoupper($text);
        }

        // 2. If text contains TrxID / TRX ID pattern followed by exactly 10 alphanumeric characters (e.g., in bKash SMS or copy-paste)
        if (preg_match('/(?:trx\s*id|tx\s*id|transaction\s*id|txn\s*id)[\s:\-#=]*([A-Za-z0-9]{10})(?![A-Za-z0-9])/i', $text, $matches)) {
            return strtoupper($matches[1]);
        }

        // 3. Strip any leading TrxID / TRX ID prefix
        $stripped = (string) preg_replace('/^(?:trx\s*id|tx\s*id|transaction\s*id|txn\s*id)[\s:\-#=]*/i', '', $text);

        // 4. Remove all non-alphanumeric characters and convert to uppercase
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $stripped));
    }

    public function __construct(
        protected array $config = []
    ) {}

    /**
     * Return the unique driver identifier.
     */
    public function getDriverName(): string
    {
        return 'bkash_manual';
    }

    /**
     * Indicate manual verification support.
     */
    public function supportsManualVerification(): bool
    {
        return true;
    }

    /**
     * Check if a bKash Transaction ID already exists in leads or invoices.
     */
    public function isDuplicate(string $txid, ?int $excludeLeadId = null): bool
    {
        return $this->findDuplicate($txid, $excludeLeadId) !== null;
    }

    /**
     * Find existing collision in leads or invoices for a given TxID.
     */
    public function findDuplicate(string $txid, ?int $excludeLeadId = null): ?array
    {
        $normalized = self::extractTxid($txid);

        if (empty($normalized)) {
            return null;
        }

        // Query leads
        $leadQuery = DB::table('leads')
            ->where('bkash_txid', $normalized)
            ->whereNull('deleted_at');

        if ($excludeLeadId !== null) {
            $leadQuery->where('id', '!=', $excludeLeadId);
        }

        $leadMatch = $leadQuery->first(['id', 'workspace_id', 'status', 'first_name', 'last_name']);
        if ($leadMatch) {
            return [
                'source' => 'leads',
                'id' => $leadMatch->id,
                'lead_id' => $leadMatch->id,
                'applicant_name' => trim(($leadMatch->first_name ?? '') . ' ' . ($leadMatch->last_name ?? '')),
                'status' => $leadMatch->status,
            ];
        }

        // Query invoices
        $invoiceMatch = DB::table('invoices')
            ->where('payment_reference', $normalized)
            ->whereIn('status', ['paid', 'unpaid'])
            ->first(['id', 'workspace_id', 'invoice_number', 'status']);

        if ($invoiceMatch) {
            return [
                'source' => 'invoices',
                'id' => $invoiceMatch->id,
                'invoice_number' => $invoiceMatch->invoice_number ?? null,
                'status' => $invoiceMatch->status,
            ];
        }

        return null;
    }

    /**
     * Verify bKash payment per DEC-05.
     */
    public function verifyPayment(PaymentVerificationRequest $request): PaymentVerificationResult
    {
        $txid = self::extractTxid((string) $request->paymentReference);

        // 1. Regex assertion (exact 10 uppercase alphanumeric characters)
        if (!preg_match(self::TXID_REGEX, $txid)) {
            return PaymentVerificationResult::rejected(
                reference: $txid,
                rejectionReasonCode: 'INVALID_TXID',
                errorMessage: 'bKash Transaction ID must be exactly 10 uppercase alphanumeric characters (e.g. BL92A8ZK91).',
                evidencePayload: [
                    'stream' => 'payment',
                    'status' => 'rejected',
                    'bkash_txid' => $txid,
                    'raw_input' => $request->paymentReference,
                    'rejection_reason_code' => 'INVALID_TXID',
                    'rejection_notes' => 'Malformed transaction identifier format.',
                ]
            );
        }

        // 2. Global uniqueness assertion across leads and invoices
        $collision = $this->findDuplicate($txid, $request->leadId);
        if ($collision !== null) {
            $sourceDesc = $collision['source'] === 'leads'
                ? "Lead #{$collision['id']} ({$collision['applicant_name']})"
                : "Invoice #{$collision['id']}";

            return PaymentVerificationResult::rejected(
                reference: $txid,
                rejectionReasonCode: 'DUPLICATE_TXID',
                errorMessage: "Transaction ID {$txid} is already registered to another record: {$sourceDesc}.",
                evidencePayload: [
                    'stream' => 'payment',
                    'status' => 'rejected',
                    'bkash_txid' => $txid,
                    'rejection_reason_code' => 'DUPLICATE_TXID',
                    'collision' => $collision,
                    'rejection_notes' => "Duplicate TxID detected against {$collision['source']} ID {$collision['id']}.",
                ]
            );
        }

        // 3. Construct structured evidence payload per VERIFICATION-DICTIONARY.md Section 3.4
        $evidencePayload = [
            'stream' => 'payment',
            'status' => 'verified',
            'verified_by_user_id' => $request->reviewerUserId,
            'verified_at' => now()->toIso8601String(),
            'bkash_txid' => $txid,
            'amount_received_bdt' => $request->amountBdt,
            'expected_amount_bdt' => $request->amountBdt,
            'payment_method' => 'bkash_manual',
            'merchant_statement_date' => now()->toDateString(),
            'payment_verification_notes' => $request->notes ?? 'Confirmed in bKash Merchant statement.',
            'rejection_reason_code' => null,
            'rejection_notes' => null,
        ];

        return PaymentVerificationResult::success(
            reference: $txid,
            evidencePayload: $evidencePayload,
            status: 'verified'
        );
    }

    /**
     * Initiate an outbound payment session.
     * Manual driver does not support online payment initiation per DEC-05.
     *
     * @throws UnsupportedPaymentOperationException
     */
    public function initiatePayment(PaymentInitiationRequest $request): PaymentRedirectResponse
    {
        throw new UnsupportedPaymentOperationException(
            'Driver [bkash_manual] does not support online payment initiation. Manual TxID verification is required per DEC-05.'
        );
    }

    /**
     * Process an asynchronous gateway webhook.
     * Manual driver does not support automated webhook callbacks.
     *
     * @throws UnsupportedPaymentOperationException
     */
    public function handleWebhook(Request $request): WebhookResult
    {
        throw new UnsupportedPaymentOperationException(
            'Driver [bkash_manual] does not support automated webhook callbacks.'
        );
    }
}
