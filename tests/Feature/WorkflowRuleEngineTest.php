<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\WorkflowRule;
use App\Domain\Mail\Services\WorkflowRuleEngine;
use App\Domain\Members\Models\Member;
use App\Jobs\DispatchOfficialEmailJob;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class WorkflowRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeder_populates_all_nine_canonical_workflow_rules_linked_to_templates(): void
    {
        $rules = WorkflowRule::withoutGlobalScopes()
            ->with('template')
            ->where('workspace_id', 'ws-emk-dhaka')
            ->get();

        $this->assertCount(9, $rules);

        $triggerEvents = $rules->pluck('trigger_event')->all();
        foreach (WorkflowRule::ALLOWED_TRIGGERS as $allowedTrigger) {
            $this->assertContains($allowedTrigger, $triggerEvents);
        }

        $renewalRule = $rules->firstWhere('trigger_event', WorkflowRule::TRIGGER_RENEWAL_WINDOW_OPEN);
        $this->assertNotNull($renewalRule);
        $this->assertSame(30, $renewalRule->days_offset);
        $this->assertTrue($renewalRule->is_enabled);
        $this->assertNotNull($renewalRule->template);
        $this->assertSame(EmailTemplate::MILESTONE_RENEWAL_WINDOW, $renewalRule->template->template_key);
    }

    public function test_workflow_rule_engine_triggers_email_when_enabled_and_skips_when_disabled(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        $term = MembershipTerm::withoutGlobalScopes()->firstOrFail();
        $member = $this->createMemberRecord(
            memberIdCode: 'EMK-2026-00201',
            email: 'member.card@example.com',
            status: 'active',
            expiryDate: now()->addYear()->toDateString(),
            termId: $term->id,
            txid: 'WFRLTX0201'
        );

        /** @var WorkflowRuleEngine $engine */
        $engine = app(WorkflowRuleEngine::class);

        $dispatched = $engine->trigger(
            workspaceId: 'ws-emk-dhaka',
            triggerEvent: WorkflowRule::TRIGGER_ID_CARD_READY,
            recipientEmail: $member->email,
            variables: [
                'member_name' => 'Nadia Islam',
                'member_id' => 'EMK-2026-00201',
                'workspace_name' => 'EMK Center Dhaka',
                'action_url' => 'https://hub.emkcenter.org/portal',
            ],
            memberId: $member->id
        );

        $this->assertNotNull($dispatched);
        $this->assertSame('queued', $dispatched->status);
        $this->assertSame(EmailTemplate::MILESTONE_ID_CARD_READY, $dispatched->template_key);
        Bus::assertDispatchedTimes(DispatchOfficialEmailJob::class, 1);

        // Now disable the lead.rejected rule and verify trigger() returns null without dispatching
        WorkflowRule::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->where('trigger_event', WorkflowRule::TRIGGER_LEAD_REJECTED)
            ->update(['is_enabled' => false]);

        $skipped = $engine->trigger(
            workspaceId: 'ws-emk-dhaka',
            triggerEvent: WorkflowRule::TRIGGER_LEAD_REJECTED,
            recipientEmail: 'rejected.applicant@example.com',
            variables: [
                'applicant_name' => 'Kamal Hossain',
                'workspace_name' => 'EMK Center Dhaka',
                'reason' => 'Invalid bKash Transaction ID.',
            ],
            leadId: 404
        );

        $this->assertNull($skipped);
        Bus::assertDispatchedTimes(DispatchOfficialEmailJob::class, 1);
    }

    public function test_workflow_rule_engine_rejects_unknown_trigger_event_outside_allowlist(): void
    {
        /** @var WorkflowRuleEngine $engine */
        $engine = app(WorkflowRuleEngine::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported workflow trigger event: system.arbitrary_eval');

        $engine->trigger(
            workspaceId: 'ws-emk-dhaka',
            triggerEvent: 'system.arbitrary_eval',
            recipientEmail: 'attacker@example.com'
        );
    }

    public function test_process_membership_milestones_command_dispatches_renewal_window_and_transitions_expired_members_idempotently(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        $term = MembershipTerm::withoutGlobalScopes()->firstOrFail();

        // 1. Member expiring in 25 days (inside the 30-day renewal window)
        $expiringSoonMember = $this->createMemberRecord(
            memberIdCode: 'EMK-2026-00301',
            email: 'renewing.soon@example.com',
            status: 'active',
            expiryDate: now()->addDays(25)->toDateString(),
            termId: $term->id,
            txid: 'WFRLTX0301'
        );

        // 2. Member expiring in 60 days (outside the 30-day renewal window)
        $farFutureMember = $this->createMemberRecord(
            memberIdCode: 'EMK-2026-00302',
            email: 'far.future@example.com',
            status: 'active',
            expiryDate: now()->addDays(60)->toDateString(),
            termId: $term->id,
            txid: 'WFRLTX0302'
        );

        // 3. Member whose expiry_date was yesterday (expired)
        $expiredMember = $this->createMemberRecord(
            memberIdCode: 'EMK-2026-00303',
            email: 'expired.yesterday@example.com',
            status: 'active',
            expiryDate: now()->subDay()->toDateString(),
            termId: $term->id,
            txid: 'WFRLTX0303'
        );

        $exitCode = Artisan::call('crm:process-membership-milestones', [
            '--workspace' => 'ws-emk-dhaka',
        ]);

        $this->assertSame(0, $exitCode);

        // Renewal window member: remains active, receives renewal_window email
        $this->assertSame('active', $expiringSoonMember->fresh()->status);
        $renewalMsg = EmailMessage::withoutGlobalScopes()
            ->where('member_id', $expiringSoonMember->id)
            ->where('template_key', EmailTemplate::MILESTONE_RENEWAL_WINDOW)
            ->first();
        $this->assertNotNull($renewalMsg);
        $this->assertStringContainsString('EMK-2026-00301', (string) $renewalMsg->subject);

        // Far future member: receives no email
        $this->assertSame(
            0,
            EmailMessage::withoutGlobalScopes()->where('member_id', $farFutureMember->id)->count()
        );

        // Expired member: transitioned to 'inactive', audit logged, and membership_expiry email queued
        $this->assertSame('inactive', $expiredMember->fresh()->status);

        $expiryAudit = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_MEMBER_STATUS_CHANGED)
            ->where('auditable_type', Member::class)
            ->where('auditable_id', $expiredMember->id)
            ->first();
        $this->assertNotNull($expiryAudit);
        $this->assertSame('active', $expiryAudit->old_values['status'] ?? null);
        $this->assertSame('inactive', $expiryAudit->new_values['status'] ?? null);

        $expiryMsg = EmailMessage::withoutGlobalScopes()
            ->where('member_id', $expiredMember->id)
            ->where('template_key', EmailTemplate::MILESTONE_EXPIRY)
            ->first();
        $this->assertNotNull($expiryMsg);

        // Total jobs dispatched on first run: 2 (1 renewal + 1 expiry)
        Bus::assertDispatchedTimes(DispatchOfficialEmailJob::class, 2);

        // Run command a second time on the same day -> must be completely idempotent (no duplicate emails or jobs)
        Artisan::call('crm:process-membership-milestones', [
            '--workspace' => 'ws-emk-dhaka',
        ]);

        $this->assertSame(
            1,
            EmailMessage::withoutGlobalScopes()
                ->where('member_id', $expiringSoonMember->id)
                ->where('template_key', EmailTemplate::MILESTONE_RENEWAL_WINDOW)
                ->count()
        );
        $this->assertSame(
            1,
            EmailMessage::withoutGlobalScopes()
                ->where('member_id', $expiredMember->id)
                ->where('template_key', EmailTemplate::MILESTONE_EXPIRY)
                ->count()
        );
        Bus::assertDispatchedTimes(DispatchOfficialEmailJob::class, 2);
    }

    private function createMemberRecord(
        string $memberIdCode,
        string $email,
        string $status,
        string $expiryDate,
        int $termId,
        string $txid
    ): Member {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => $txid,
            'first_name' => 'Member',
            'last_name' => $memberIdCode,
            'email' => $email,
            'contact_number' => '+8801711999000',
            'date_of_birth' => '1996-06-15',
            'age_at_submission' => 30,
            'sex' => 'female',
            'address_house_no' => '15',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'service',
            'institution_study' => 'DU',
            'highest_certification' => 'masters',
            'area_of_subject' => 'Public Policy',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now()->subYear(),
        ]);

        $portalUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Member ' . $memberIdCode,
            'email' => $email,
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $memberIdCode,
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $termId,
            'status' => $status,
            'first_name' => 'Member',
            'last_name' => $memberIdCode,
            'email' => $email,
            'contact_number' => '+8801711999000',
            'date_of_birth' => '1996-06-15',
            'sex' => 'female',
            'address_house_no' => '15',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'service',
            'institution_study' => 'DU',
            'highest_certification' => 'masters',
            'area_of_subject' => 'Public Policy',
            'start_date' => now()->subYear()->toDateString(),
            'expiry_date' => $expiryDate,
        ]);
    }
}
