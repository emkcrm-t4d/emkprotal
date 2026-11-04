<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Exceptions\UnmetConversionInvariantException;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberIdSequenceGenerator;
use App\Jobs\SendMemberWelcomeEmailJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class LeadConversionService
{
    public function __construct(
        private readonly MemberIdSequenceGenerator $sequenceGenerator
    ) {}

    /**
     * Execute the atomic 10-step lead conversion transaction.
     *
     * @throws UnmetConversionInvariantException
     * @throws UnauthorizedVerificationException
     */
    public function convert(Lead $lead, int $termId, User|int $managerUser): Member
    {
        $manager = $managerUser instanceof User ? $managerUser : User::findOrFail($managerUser);

        // Workspace manager authorization guard per MODULE-REGISTRY.md (leads.convert)
        if (!$manager->can('leads.convert') && !$manager->hasRole(['workspace_manager', 'superadmin', 'global_admin'])) {
            throw new UnauthorizedVerificationException(
                "User [{$manager->id}] does not possess managerial permission [leads.convert]."
            );
        }

        if (!$manager->hasWorkspaceAccess((string) $lead->workspace_id)) {
            throw new UnauthorizedVerificationException(
                "User [{$manager->id}] does not have access to workspace [{$lead->workspace_id}]."
            );
        }

        return DB::transaction(function () use ($lead, $termId, $manager): Member {
            $now = Carbon::now();
            $year = (int) $now->year;
            $spaceCode = !empty($lead->target_space_code) ? $lead->target_space_code : 'EMK';

            // --- LOCK ACQUISITION HIERARCHY (Deadlock Prevention per Section 3.2) ---
            // 1. Lock sequence counter first
            $memberId = $this->sequenceGenerator->nextMemberId($spaceCode, $year);

            // 2. Lock lead row second
            /** @var Lead $lockedLead */
            $lockedLead = Lead::where('id', $lead->id)->lockForUpdate()->firstOrFail();

            // 3. Lock membership term third
            /** @var MembershipTerm $term */
            $term = MembershipTerm::where('id', $termId)->lockForUpdate()->firstOrFail();

            // --- STEP 1: VERIFY INVARIANTS (DEC-03) ---
            $this->assertConversionInvariants($lockedLead);

            // --- STEP 3 & 4: COMPUTE DATES (DEC-08) & CREATE MEMBER ---
            $memberUuid = (string) Str::uuid();
            $startDate = $now->copy();
            $expiryDate = $startDate->copy()->addMonths((int) $term->duration_months);

            // Provision portal account (Step 9 preparation per DEC-06)
            $portalUserId = $this->provisionPortalUser($lockedLead);

            $member = Member::create([
                'uuid' => $memberUuid,
                'member_id' => $memberId,
                'workspace_id' => $lockedLead->workspace_id,
                'lead_id' => $lockedLead->id,
                'user_id' => $portalUserId,
                'membership_term_id' => $term->id,
                'status' => 'active',
                'first_name' => $lockedLead->first_name,
                'middle_name' => $lockedLead->middle_name,
                'last_name' => $lockedLead->last_name,
                'email' => $lockedLead->email,
                'contact_number' => $lockedLead->contact_number,
                'date_of_birth' => $lockedLead->date_of_birth,
                'sex' => $lockedLead->sex,
                'address_house_no' => $lockedLead->address_house_no,
                'address_road_no' => $lockedLead->address_road_no,
                'address_area' => $lockedLead->address_area,
                'address_zip_code' => $lockedLead->address_zip_code,
                'address_division' => $lockedLead->address_division,
                'address_district' => $lockedLead->address_district,
                'occupation' => $lockedLead->occupation,
                'institution_study' => $lockedLead->institution_study,
                'highest_certification' => $lockedLead->highest_certification,
                'area_of_subject' => $lockedLead->area_of_subject,
                'start_date' => $startDate->toDateString(),
                'expiry_date' => $expiryDate->toDateString(),
            ]);

            // --- STEP 5: RE-ASSOCIATE PRIVATE DOCUMENTS ---
            $this->reassociatePrivateAttachments($lockedLead, $member);

            // --- STEP 6: GENERATE POST-CONVERSION PAID INVOICE (DEC-03) ---
            $invoiceNumber = sprintf('INV-%04d-%s', $year, substr($memberId, -5));
            Invoice::create([
                'invoice_number' => $invoiceNumber,
                'workspace_id' => $lockedLead->workspace_id,
                'member_id' => $member->id,
                'lead_id' => $lockedLead->id,
                'amount_bdt' => $term->fee_bdt,
                'payment_method' => 'bkash_manual',
                'payment_reference' => $lockedLead->bkash_txid,
                'status' => 'paid',
                'paid_at' => $now,
            ]);

            // --- STEP 7: CREATE ID CARD IN PRINTING QUEUE ---
            IdCard::create([
                'workspace_id' => $lockedLead->workspace_id,
                'member_id' => $member->id,
                'card_number' => $memberId,
                'status' => 'provided_for_printing',
            ]);

            // --- STEP 8: UPDATE LEAD STATUS ---
            $lockedLead->update([
                'status' => 'converted',
                'converted_member_id' => $member->id,
            ]);

            // --- STEP 9: DISPATCH WELCOME EMAIL VIA HORIZON ---
            SendMemberWelcomeEmailJob::dispatch($member->id);

            // --- STEP 10: EMIT IMMUTABLE AUDIT LOG (DEC-11) ---
            AuditLog::create([
                'workspace_id' => $lockedLead->workspace_id,
                'actor_id' => $manager->id,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_CONVERSION_COMPLETED,
                'auditable_type' => Member::class,
                'auditable_id' => $member->id,
                'new_values' => [
                    'member_id' => $memberId,
                    'lead_id' => $lockedLead->id,
                    'invoice_number' => $invoiceNumber,
                    'term_code' => $term->code,
                    'start_date' => $startDate->toDateString(),
                    'expiry_date' => $expiryDate->toDateString(),
                ],
                'reason' => 'Standard lead conversion following completed verification streams.',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return $member;
        });
    }

    /**
     * Assert all mandatory conversion invariants.
     *
     * @throws UnmetConversionInvariantException
     */
    public function assertConversionInvariants(Lead $lead): void
    {
        $unmet = [];

        if ($lead->payment_verification_status !== 'verified') {
            $unmet['payment'] = 'Payment verification stream is not Complete/Verified.';
        }
        if ($lead->document_verification_status !== 'verified') {
            $unmet['document'] = 'Document verification stream is not Complete/Verified.';
        }
        if ($lead->photo_verification_status !== 'verified') {
            $unmet['photo'] = 'Photo verification stream is not Complete/Verified.';
        }
        if ($lead->status !== 'approved') {
            $unmet['lead_status'] = 'Lead overall status must be Approved prior to conversion.';
        }
        if ((int) $lead->age_at_submission < 18 && !$lead->has_parental_consent) {
            $unmet['parental_consent'] = 'Applicant under 18 lacks verified parental consent form.';
        }

        // Assert email uniqueness in active members
        $existingMember = Member::where('email', $lead->email)
            ->whereIn('status', ['active', 'inactive', 'restricted'])
            ->exists();

        if ($existingMember) {
            $unmet['email_conflict'] = 'An active or existing member record already exists for this email.';
        }

        if (!empty($unmet)) {
            throw new UnmetConversionInvariantException(
                'Conversion blocked: One or more domain invariants are unsatisfied.',
                $unmet
            );
        }
    }

    /**
     * Check if conversion is allowed for the lead.
     */
    public function isConversionAllowed(Lead $lead): bool
    {
        try {
            $this->assertConversionInvariants($lead);
            return true;
        } catch (UnmetConversionInvariantException) {
            return false;
        }
    }

    /**
     * Get unmet conversion conditions as an associative array.
     *
     * @return array<string, string>
     */
    public function getUnmetConversionInvariants(Lead $lead): array
    {
        try {
            $this->assertConversionInvariants($lead);
            return [];
        } catch (UnmetConversionInvariantException $e) {
            return $e->getUnmetConditions();
        }
    }

    /**
     * Provision read-only portal account with forced password reset.
     */
    private function provisionPortalUser(Lead $lead): int
    {
        $existing = DB::table('users')->where('email', $lead->email)->first();
        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update([
                'workspace_id' => $lead->workspace_id,
                'name' => trim("{$lead->first_name} {$lead->last_name}"),
                'guard' => 'member',
                'is_active' => true,
                'must_reset_password' => true,
                'updated_at' => Carbon::now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('users')->insertGetId([
            'workspace_id' => $lead->workspace_id,
            'name' => trim("{$lead->first_name} {$lead->last_name}"),
            'email' => $lead->email,
            'password' => null, // Account inactive until password set via email token
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Move private documents from lead directory to member directory.
     */
    private function reassociatePrivateAttachments(Lead $lead, Member $member): void
    {
        $disk = Storage::disk('private');
        $leadDir = "workspaces/{$lead->workspace_id}/leads/{$lead->uuid}";
        $memberDir = "workspaces/{$member->workspace_id}/members/{$member->uuid}";

        if ($disk->exists($leadDir)) {
            $files = $disk->allFiles($leadDir);
            foreach ($files as $file) {
                $target = str_replace($leadDir, $memberDir, $file);
                $disk->copy($file, $target);
            }
        }
    }
}
