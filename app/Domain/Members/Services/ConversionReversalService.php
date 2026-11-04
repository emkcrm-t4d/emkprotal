<?php

declare(strict_types=1);

namespace App\Domain\Members\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Exceptions\InvalidConversionReversalException;
use App\Domain\Members\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ConversionReversalService
{
    /**
     * Reversal statutory cutoff: 30 calendar days per DEC-04 and CONVERSION-SPEC.md.
     */
    public const STATUTORY_CUTOFF_DAYS = 30;

    /**
     * Mandatory justification minimum character length.
     */
    public const MIN_JUSTIFICATION_CHARS = 20;

    /**
     * Evaluate whether a member conversion is eligible for reversal.
     *
     * @return array{allowed: bool, reasons: array<string, string>}
     */
    public function canReverse(Member $member): array
    {
        $reasons = [];

        // 1. Statutory 30-day cutoff evaluation
        $conversionDate = Carbon::parse($member->created_at);
        if ($conversionDate->diffInDays(Carbon::now()) > self::STATUTORY_CUTOFF_DAYS) {
            $reasons['cutoff'] = 'Statutory 30-day cutoff expired. Conversion cannot be reversed after 30 calendar days.';
        }

        // 2. Member status purity
        if ($member->status !== 'active') {
            $reasons['status'] = "Cannot reverse member in status '{$member->status}'.";
        }

        // 3. Subsequent invoices guard
        $initialInvoice = Invoice::where('member_id', $member->id)
            ->where('lead_id', $member->lead_id)
            ->oldest()
            ->first();

        $subsequentInvoices = Invoice::where('member_id', $member->id)
            ->when($initialInvoice, fn ($q) => $q->where('id', '!=', $initialInvoice->id))
            ->where(function ($q) use ($member) {
                $q->where('created_at', '>', $member->created_at)
                  ->orWhereNull('lead_id')
                  ->orWhere('lead_id', '!=', $member->lead_id);
            })
            ->exists();

        if ($subsequentInvoices) {
            $reasons['subsequent_invoices'] = 'Reversal prohibited: Member has subsequent invoices or renewal transactions.';
        }

        // 4. Physical card delivery guard
        $cardDelivered = IdCard::where('member_id', $member->id)
            ->where('status', 'delivered')
            ->exists();

        if ($cardDelivered) {
            $reasons['card_delivered'] = 'Reversal prohibited: Physical membership card has already been delivered to the member.';
        }

        return [
            'allowed' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Execute the cascading 30-day conversion reversal protocol per DEC-04, ARCH-04, and CONVERSION-SPEC.md.
     *
     * @throws InvalidConversionReversalException
     */
    public function reverse(Member $member, string $justification, User|int $managerUser): void
    {
        $managerUserId = $managerUser instanceof User ? $managerUser->id : (int) $managerUser;
        $manager = $managerUser instanceof User ? $managerUser : User::find($managerUserId);

        // 1. Managerial role/permission authorization check
        if ($manager && !$manager->can('leads.reverse_conversion') && !$manager->hasRole(['workspace_manager', 'superadmin', 'global_admin'])) {
            throw new InvalidConversionReversalException(
                "User [{$manager->id}] does not possess managerial permission [leads.reverse_conversion].",
                ['permission' => 'Workspace Manager authority (leads.reverse_conversion) required.'],
                403
            );
        }

        // 2. Workspace tenancy defense
        if ($manager && method_exists($manager, 'hasWorkspaceAccess') && !$manager->hasWorkspaceAccess((string) $member->workspace_id)) {
            throw new InvalidConversionReversalException(
                "User [{$manager->id}] does not have access to workspace [{$member->workspace_id}].",
                ['workspace' => 'Cross-workspace reversal prohibited.'],
                403
            );
        }

        // 3. Justification length guard (minimum 20 characters)
        $trimmedJustification = trim($justification);
        if (mb_strlen($trimmedJustification) < self::MIN_JUSTIFICATION_CHARS) {
            throw new InvalidConversionReversalException(
                sprintf('Conversion reversal requires a mandatory justification of at least %d characters.', self::MIN_JUSTIFICATION_CHARS),
                ['justification' => 'Justification string too short.']
            );
        }

        DB::transaction(function () use ($member, $trimmedJustification, $managerUserId): void {
            /** @var Member $lockedMember */
            $lockedMember = Member::where('id', $member->id)->lockForUpdate()->firstOrFail();

            // 4. Validate statutory cutoff (30 calendar days)
            $conversionDate = Carbon::parse($lockedMember->created_at);
            if ($conversionDate->diffInDays(Carbon::now()) > self::STATUTORY_CUTOFF_DAYS) {
                throw new InvalidConversionReversalException(
                    'Statutory 30-day cutoff expired. Conversion cannot be reversed after 30 calendar days.',
                    ['cutoff' => 'Conversion date exceeds 30-calendar-day statutory limit.']
                );
            }

            // 5. Validate member status purity
            if ($lockedMember->status !== 'active') {
                throw new InvalidConversionReversalException(
                    "Cannot reverse member in status '{$lockedMember->status}'.",
                    ['status' => 'Member must be active to reverse conversion.']
                );
            }

            // 6. Assert no subsequent invoices exist
            $initialInvoice = Invoice::where('member_id', $lockedMember->id)
                ->where('lead_id', $lockedMember->lead_id)
                ->oldest()
                ->first();

            $subsequentInvoices = Invoice::where('member_id', $lockedMember->id)
                ->when($initialInvoice, fn ($q) => $q->where('id', '!=', $initialInvoice->id))
                ->where(function ($q) use ($lockedMember) {
                    $q->where('created_at', '>', $lockedMember->created_at)
                      ->orWhereNull('lead_id')
                      ->orWhere('lead_id', '!=', $lockedMember->lead_id);
                })
                ->exists();

            if ($subsequentInvoices) {
                throw new InvalidConversionReversalException(
                    'Reversal prohibited: Member has subsequent invoices or renewal transactions.',
                    ['subsequent_invoices' => 'Subsequent invoices exist for this member.']
                );
            }

            // 7. Assert card has not been handed over to member
            $cardDelivered = IdCard::where('member_id', $lockedMember->id)
                ->where('status', 'delivered')
                ->exists();

            if ($cardDelivered) {
                throw new InvalidConversionReversalException(
                    'Reversal prohibited: Physical membership card has already been delivered to the member.',
                    ['card_delivered' => 'Physical membership card is already delivered.']
                );
            }

            // --- EXECUTE CASCADING ROLLBACK (Step 8 per CONVERSION-SPEC.md Section 6.2) ---

            // A. Update Member status to archived_reversed
            $lockedMember->update([
                'status' => 'archived_reversed',
            ]);

            // B. Disable portal user account & evict remember_token
            if ($lockedMember->user_id) {
                User::where('id', $lockedMember->user_id)->update([
                    'is_active' => false,
                    'remember_token' => null,
                ]);
            }

            // C. Mark post-conversion invoice void_reversed
            Invoice::where('member_id', $lockedMember->id)
                ->where('lead_id', $lockedMember->lead_id)
                ->update([
                    'status' => 'void_reversed',
                ]);

            // D. Void plastic ID cards
            IdCard::where('member_id', $lockedMember->id)->update([
                'status' => 'voided_reversal',
            ]);

            // E. Restore Lead status to under_review, nullify converted_member_id (preserve verifications & files)
            if ($lockedMember->lead_id) {
                Lead::where('id', $lockedMember->lead_id)->update([
                    'status' => 'under_review',
                    'converted_member_id' => null,
                ]);
            }

            // F. Append immutable audit event in audit_logs
            AuditLog::create([
                'workspace_id' => $lockedMember->workspace_id,
                'actor_id' => $managerUserId,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_CONVERSION_REVERSED,
                'auditable_type' => Member::class,
                'auditable_id' => $lockedMember->id,
                'old_values' => ['status' => 'active'],
                'new_values' => [
                    'status' => 'archived_reversed',
                    'lead_id' => $lockedMember->lead_id,
                    'restored_lead_status' => 'under_review',
                ],
                'reason' => $trimmedJustification,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);
        });
    }
}
