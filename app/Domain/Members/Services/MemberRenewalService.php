<?php

declare(strict_types=1);

namespace App\Domain\Members\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Mail\Models\WorkflowRule;
use App\Domain\Mail\Services\WorkflowRuleEngine;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class MemberRenewalService
{
    public function __construct(
        protected ?WorkflowRuleEngine $workflowEngine = null
    ) {
    }

    /**
     * Determine if a member is currently eligible to submit a renewal request.
     * Inactive (expired) members may renew at any time.
     * Active members can renew starting 30 calendar days prior to their expiration date.
     * Revoked members cannot renew.
     */
    public function isRenewalWindowOpen(Member $member): bool
    {
        if ($member->status === 'revoked') {
            return false;
        }

        if ($member->status === 'inactive') {
            return true;
        }

        if ($member->status === 'active') {
            if (! $member->expiry_date) {
                return false;
            }

            $today = Carbon::today();
            $expiry = Carbon::parse($member->expiry_date)->startOfDay();

            return $today->diffInDays($expiry, false) <= 30;
        }

        return false;
    }

    /**
     * Submit a membership renewal request with term selection and bKash transaction verification.
     *
     * @throws DomainException
     */
    public function submitRenewalRequest(
        Member $member,
        int $termId,
        string $bkashTxid
    ): MemberChangeRequest {
        if (! $this->isRenewalWindowOpen($member)) {
            throw new DomainException('Renewal window is not open. You can renew starting 30 days prior to your membership expiration.');
        }

        // Concurrency guard: Ensure no pending renewal request exists
        $hasPending = MemberChangeRequest::withoutGlobalScopes()
            ->where('member_id', $member->id)
            ->where('request_type', MemberChangeRequest::TYPE_RENEWAL)
            ->where('status', MemberChangeRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            throw new DomainException('A renewal request is already pending review for this membership.');
        }

        // Term validation: active MembershipTerm belonging to member's workspace
        $term = MembershipTerm::withoutGlobalScopes()
            ->where('id', $termId)
            ->where('workspace_id', $member->workspace_id)
            ->where('is_active', true)
            ->first();

        if (! $term) {
            throw new DomainException('Selected membership term is invalid or inactive for this workspace.');
        }

        // bKash Transaction ID validation per DEC-05:
        // Must be exact 10 uppercase alphanumeric characters
        $trimmedTxid = trim($bkashTxid);
        if (! preg_match('/^[A-Z0-9]{10}$/', $trimmedTxid)) {
            throw new DomainException('bKash Transaction ID must be exactly 10 uppercase alphanumeric characters.');
        }
        $cleanTxid = $trimmedTxid;

        // System-wide uniqueness check across leads, invoices, and pending change requests
        $existsInLeads = Lead::withoutGlobalScopes()
            ->where('bkash_txid', $cleanTxid)
            ->exists();

        $existsInInvoices = Invoice::withoutGlobalScopes()
            ->where('payment_reference', $cleanTxid)
            ->exists();

        $existsInPending = MemberChangeRequest::withoutGlobalScopes()
            ->where('status', MemberChangeRequest::STATUS_PENDING)
            ->where('new_payload->bkash_txid', $cleanTxid)
            ->exists();

        if ($existsInLeads || $existsInInvoices || $existsInPending) {
            throw new DomainException('This bKash Transaction ID has already been submitted or verified.');
        }

        return DB::transaction(function () use ($member, $term, $cleanTxid): MemberChangeRequest {
            $oldPayload = [
                'expiry_date' => $member->expiry_date?->toDateString(),
                'term_id' => $member->membership_term_id,
                'status' => $member->status,
            ];

            $newPayload = [
                'term_id' => $term->id,
                'term_name' => $term->name,
                'duration_months' => $term->duration_months,
                'fee_bdt' => (string) $term->fee_bdt,
                'bkash_txid' => $cleanTxid,
            ];

            $justification = "Membership renewal for term: {$term->name}";

            $request = MemberChangeRequest::create([
                'workspace_id' => $member->workspace_id,
                'member_id' => $member->id,
                'request_type' => MemberChangeRequest::TYPE_RENEWAL,
                'status' => MemberChangeRequest::STATUS_PENDING,
                'old_payload' => $oldPayload,
                'new_payload' => $newPayload,
                'justification' => $justification,
            ]);

            $actorId = auth('member')->id() ?? auth()->id() ?? $member->user_id;
            $actorGuard = auth('member')->check() ? 'member' : (auth()->check() ? 'web' : 'system');

            AuditLog::create([
                'workspace_id' => $member->workspace_id,
                'actor_id' => $actorId,
                'actor_guard' => $actorGuard,
                'event_type' => AuditLog::EVENT_MEMBER_RENEWAL_SUBMITTED,
                'auditable_type' => MemberChangeRequest::class,
                'auditable_id' => $request->id,
                'old_values' => $oldPayload,
                'new_values' => $newPayload,
                'reason' => $justification,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'MemberPortal',
                'created_at' => now(),
            ]);

            return $request;
        });
    }

    /**
     * Atomically approve a renewal request, perform dynamic expiry calculations,
     * generate a paid invoice record, and transition the member status.
     *
     * @throws AuthorizationException|DomainException
     */
    public function approveRenewal(
        MemberChangeRequest $request,
        User $reviewerUser,
        ?string $managerNotes = null
    ): Member {
        $this->assertReviewerAuthorized($reviewerUser, $request);

        if ($request->request_type !== MemberChangeRequest::TYPE_RENEWAL) {
            throw new DomainException("Request #{$request->id} is not a renewal request.");
        }

        if ($request->status !== MemberChangeRequest::STATUS_PENDING) {
            throw new DomainException("Cannot approve a renewal request that is in status [{$request->status}].");
        }

        return DB::transaction(function () use ($request, $reviewerUser, $managerNotes): Member {
            /** @var MemberChangeRequest $lockedRequest */
            $lockedRequest = MemberChangeRequest::withoutGlobalScopes()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== MemberChangeRequest::STATUS_PENDING) {
                throw new DomainException("Cannot approve a renewal request that is in status [{$lockedRequest->status}].");
            }

            /** @var Member $lockedMember */
            $lockedMember = Member::withoutGlobalScopes()
                ->where('id', $lockedRequest->member_id)
                ->lockForUpdate()
                ->firstOrFail();

            $termId = (int) ($lockedRequest->new_payload['term_id'] ?? 0);
            /** @var MembershipTerm $term */
            $term = MembershipTerm::withoutGlobalScopes()
                ->where('id', $termId)
                ->lockForUpdate()
                ->firstOrFail();

            $bkashTxid = (string) ($lockedRequest->new_payload['bkash_txid'] ?? '');

            // Final safety assertion: TxID not already used in an invoice
            $alreadyUsedInvoice = Invoice::withoutGlobalScopes()
                ->where('payment_reference', $bkashTxid)
                ->exists();

            if ($alreadyUsedInvoice) {
                throw new DomainException('This bKash Transaction ID has already been verified in an existing invoice.');
            }

            // --- Dynamic Expiry Mathematics (STATUS-DICTIONARY.md Section 7.2) ---
            $today = Carbon::today();
            $currentExpiry = $lockedMember->expiry_date
                ? Carbon::parse($lockedMember->expiry_date)->startOfDay()
                : null;

            if ($currentExpiry && $currentExpiry->greaterThanOrEqualTo($today) && $lockedMember->status === 'active') {
                // Early renewal: preserves remaining days, adds term months to current expiry
                $newExpiry = $currentExpiry->copy()->addMonths((int) $term->duration_months);
            } else {
                // Late renewal (post-expiration or inactive member):
                // Validity starts on approval date (today)
                $lockedMember->start_date = $today->toDateString();
                $newExpiry = $today->copy()->addMonths((int) $term->duration_months);
            }

            $lockedMember->expiry_date = $newExpiry->toDateString();
            $lockedMember->membership_term_id = $term->id;
            $lockedMember->status = 'active';
            $lockedMember->save();

            // --- Generate Post-Renewal Paid Invoice (DEC-03) ---
            $invoiceNumber = $this->generateRenewalInvoiceNumber($lockedMember, $today->year);

            Invoice::create([
                'invoice_number' => $invoiceNumber,
                'workspace_id' => $lockedMember->workspace_id,
                'member_id' => $lockedMember->id,
                'lead_id' => $lockedMember->lead_id,
                'amount_bdt' => $term->fee_bdt,
                'payment_method' => 'bkash_manual',
                'payment_reference' => $bkashTxid,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $lockedRequest->update([
                'status' => MemberChangeRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $reviewerUser->id,
                'reviewed_at' => now(),
                'manager_notes' => $managerNotes,
            ]);

            // Immutable AuditLog entry
            AuditLog::create([
                'workspace_id' => $lockedMember->workspace_id,
                'actor_id' => $reviewerUser->id,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_MEMBER_RENEWAL_APPROVED,
                'auditable_type' => Member::class,
                'auditable_id' => $lockedMember->id,
                'old_values' => $lockedRequest->old_payload,
                'new_values' => array_merge($lockedRequest->new_payload, [
                    'start_date' => $lockedMember->start_date->toDateString(),
                    'expiry_date' => $lockedMember->expiry_date->toDateString(),
                    'invoice_number' => $invoiceNumber,
                ]),
                'reason' => $managerNotes ?? 'Renewal approved and payment verified by staff.',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'created_at' => now(),
            ]);

            // Trigger workflow notification
            $this->dispatchWorkflowOutcome($lockedRequest, $lockedMember, 'approved', $managerNotes ?? '');

            return $lockedMember->fresh();
        });
    }

    /**
     * Generate sequential renewal invoice number: INV-{YEAR}-{MEMBER_SEQ_5}-R(N).
     */
    protected function generateRenewalInvoiceNumber(Member $member, int $year): string
    {
        $seq = substr((string) $member->member_id, -5);
        $candidate = sprintf('INV-%04d-%s-R', $year, $seq);

        if (! Invoice::withoutGlobalScopes()->where('invoice_number', $candidate)->exists()) {
            return $candidate;
        }

        $i = 2;
        while (Invoice::withoutGlobalScopes()->where('invoice_number', "{$candidate}{$i}")->exists()) {
            $i++;
        }

        return "{$candidate}{$i}";
    }

    /**
     * Assert reviewer has valid permissions and tenancy authorization.
     *
     * @throws AuthorizationException
     */
    protected function assertReviewerAuthorized(User $reviewer, MemberChangeRequest $request): void
    {
        $isElevatedAdmin = method_exists($reviewer, 'hasRole') && $reviewer->hasRole(['superadmin', 'global_admin']);

        if (! $isElevatedAdmin) {
            if ((string) $reviewer->workspace_id !== (string) $request->workspace_id) {
                throw new AuthorizationException('Reviewer does not have access to this workspace.');
            }

            $isManager = method_exists($reviewer, 'hasRole') && $reviewer->hasRole('workspace_manager');
            $canVerifyPayment = $reviewer->can('invoices.verify_payment');
            $canEditMembers = $reviewer->can('members.edit');

            if (! $isManager && ! $canVerifyPayment && ! $canEditMembers) {
                throw new AuthorizationException('Reviewer lacks invoices.verify_payment, members.edit, or workspace_manager authority.');
            }
        }
    }

    /**
     * Trigger email notification for renewal outcome via workflow engine.
     */
    protected function dispatchWorkflowOutcome(
        MemberChangeRequest $request,
        Member $member,
        string $outcome,
        string $managerNotes
    ): void {
        try {
            $engine = $this->workflowEngine ?? app(WorkflowRuleEngine::class);
            if ($engine && $member->email) {
                $engine->trigger(
                    workspaceId: (string) $request->workspace_id,
                    triggerEvent: WorkflowRule::TRIGGER_REQUEST_OUTCOME,
                    recipientEmail: (string) $member->email,
                    variables: [
                        'first_name' => $member->first_name,
                        'member_id' => $member->member_id,
                        'request_type' => $request->request_type,
                        'outcome' => $outcome,
                        'manager_notes' => $managerNotes,
                    ],
                    memberId: $member->id,
                );
            }
        } catch (\Throwable $e) {
            // Workflow rule might not be configured; do not fail transaction
        }
    }
}
