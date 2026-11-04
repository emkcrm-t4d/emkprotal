<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Exceptions\DuplicatePaymentReferenceException;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MembershipValidityEngine;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class InvoiceService
{
    public function __construct(
        private readonly MembershipValidityEngine $validityEngine
    ) {}

    /**
     * Atomically generate a sequential invoice number for the workspace.
     * Format: INV-{YEAR}-{5_DIGIT_SEQ} (e.g. INV-2026-00001)
     */
    public function generateInvoiceNumber(string $workspaceId): string
    {
        $execute = function () use ($workspaceId): string {
            $year = (int) Carbon::now()->year;
            $prefix = sprintf('INV-%04d-', $year);

            // Pessimistic lock on workspace record to prevent concurrent collision
            Workspace::where('id', $workspaceId)->lockForUpdate()->first();

            $lastInvoice = Invoice::withoutGlobalScopes()
                ->where('invoice_number', 'like', $prefix . '%')
                ->orderByDesc('invoice_number')
                ->lockForUpdate()
                ->first();

            $nextSeq = 1;
            if ($lastInvoice && preg_match('/INV-\d{4}-(\d+)/', $lastInvoice->invoice_number, $matches)) {
                $nextSeq = ((int) $matches[1]) + 1;
            }

            return sprintf('INV-%04d-%05d', $year, $nextSeq);
        };

        return DB::transactionLevel() > 0 ? $execute() : DB::transaction($execute);
    }

    /**
     * Generate an official paid invoice following lead-to-member conversion.
     */
    public function generatePostConversionInvoice(
        Member $member,
        ?int $leadId,
        MembershipTerm $term,
        string $paymentReference
    ): Invoice {
        $invoiceNumber = $this->generateInvoiceNumber((string) $member->workspace_id);

        return Invoice::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'lead_id' => $leadId,
            'invoice_number' => $invoiceNumber,
            'amount_bdt' => $term->fee_bdt,
            'payment_method' => 'bkash_manual',
            'payment_reference' => $paymentReference,
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);
    }

    /**
     * Generate an official membership renewal invoice and extend member validity.
     * Enforces DEC-05 10-char uppercase regex and global uniqueness check.
     *
     * @throws InvalidArgumentException
     * @throws DuplicatePaymentReferenceException
     */
    public function generateRenewalInvoice(
        Member $member,
        MembershipTerm $term,
        string $bkashTxId,
        User $actor
    ): Invoice {
        // DEC-05: Exact 10-character uppercase alphanumeric bKash Transaction ID
        if (!preg_match('/^[A-Z0-9]{10}$/', $bkashTxId)) {
            throw new InvalidArgumentException(
                'bKash Transaction ID must be exactly 10 uppercase alphanumeric characters.'
            );
        }

        // Global duplicate check across leads and active invoices
        $existsInLeads = Lead::withoutGlobalScopes()
            ->where('bkash_txid', $bkashTxId)
            ->exists();

        $existsInInvoices = Invoice::withoutGlobalScopes()
            ->where('payment_reference', $bkashTxId)
            ->whereNotIn('status', ['voided', 'void_reversed'])
            ->exists();

        if ($existsInLeads || $existsInInvoices) {
            throw new DuplicatePaymentReferenceException(
                "Payment reference [{$bkashTxId}] has already been utilized."
            );
        }

        return DB::transaction(function () use ($member, $term, $bkashTxId, $actor): Invoice {
            $invoiceNumber = $this->generateInvoiceNumber((string) $member->workspace_id);

            $invoice = Invoice::create([
                'workspace_id' => $member->workspace_id,
                'member_id' => $member->id,
                'lead_id' => null,
                'invoice_number' => $invoiceNumber,
                'amount_bdt' => $term->fee_bdt,
                'payment_method' => 'bkash_manual',
                'payment_reference' => $bkashTxId,
                'status' => 'paid',
                'paid_at' => Carbon::now(),
            ]);

            // Calculate updated renewal validity dates
            $dates = $this->validityEngine->calculateRenewalDates($member, $term);

            // Update member record
            $member->update([
                'start_date' => $dates['start_date']->toDateString(),
                'expiry_date' => $dates['expiry_date']->toDateString(),
                'membership_term_id' => $term->id,
                'status' => 'active',
            ]);

            // DEC-11: Record immutable audit event
            AuditLog::create([
                'workspace_id' => $member->workspace_id,
                'actor_id' => $actor->id,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_MEMBERSHIP_RENEWED,
                'auditable_type' => Member::class,
                'auditable_id' => $member->id,
                'new_values' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'term_id' => $term->id,
                    'term_code' => $term->code,
                    'amount_bdt' => $term->fee_bdt,
                    'payment_reference' => $bkashTxId,
                    'start_date' => $dates['start_date']->toDateString(),
                    'expiry_date' => $dates['expiry_date']->toDateString(),
                    'status' => 'active',
                ],
                'reason' => 'Membership renewed with verified payment reference.',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/Console',
            ]);

            return $invoice;
        });
    }

    /**
     * Void an invoice with manager authorization and immutable audit logging.
     *
     * @throws AuthorizationException
     * @throws LogicException
     */
    public function voidInvoice(Invoice $invoice, string $reason, User $manager): Invoice
    {
        // Assert managerial authorization
        if (!$manager->can('invoices.generate') && !$manager->hasRole(['workspace_manager', 'superadmin', 'global_admin'])) {
            throw new AuthorizationException(
                "User [{$manager->id}] does not possess managerial permission to void invoices."
            );
        }

        // Assert invoice is in voidable status
        if (!in_array($invoice->status, ['paid', 'unpaid'], true)) {
            throw new LogicException("Cannot void an invoice with status [{$invoice->status}].");
        }

        $priorStatus = $invoice->status;

        $invoice->update([
            'status' => 'voided',
        ]);

        AuditLog::create([
            'workspace_id' => $invoice->workspace_id,
            'actor_id' => $manager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_INVOICE_VOIDED,
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'old_values' => [
                'status' => $priorStatus,
            ],
            'new_values' => [
                'status' => 'voided',
            ],
            'reason' => $reason,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System/Console',
        ]);

        return $invoice;
    }
}
