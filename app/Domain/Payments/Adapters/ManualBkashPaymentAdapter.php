<?php

declare(strict_types=1);

namespace App\Domain\Payments\Adapters;

use App\Domain\Payments\Contracts\PaymentAdapterInterface;
use App\Domain\Payments\DTOs\PaymentVerificationRequest;
use App\Domain\Payments\DTOs\PaymentVerificationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualBkashPaymentAdapter implements PaymentAdapterInterface
{
    public const TXID_REGEX = '/^[A-Z0-9]{10}$/';

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
        $normalized = Str::upper(trim($txid));

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
        $txid = Str::upper(trim($request->paymentReference));

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
}
