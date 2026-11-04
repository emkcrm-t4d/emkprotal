<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\IdCards\Services\IdCardLifecycleService;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadConversionService;
use App\Domain\Mail\Mailables\TemplatedLifecycleMail;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Mail\Services\ImapMailboxAdapter;
use App\Domain\Mail\Services\MailboxSyncService;
use App\Domain\Mail\Services\QueuedEmailDispatcher;
use App\Domain\Members\Models\Member;
use App\Domain\Workspaces\Models\Workspace;
use App\Jobs\DispatchOfficialEmailJob;
use App\Livewire\Mail\EmailDeliveryMonitor;
use App\Livewire\Mail\EmailTemplateManager;
use App\Livewire\Mail\EmailThreadPanel;
use App\Livewire\Mail\WorkflowRuleManager;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Holistic Phase 8 End-to-End Integration Suite covering MAIL-01 through MAIL-05.
 */
class EndToEndOfficialMailboxAndWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $staff;
    private User $chattogramStaff;
    private MembershipTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        ImapMailboxAdapter::clearFakeMessages();
        $this->seed(DatabaseSeeder::class);

        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'American Corner Chattogram',
                'is_active' => true,
            ]
        );

        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Center Manager',
            'email' => 'e2e.manager@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');
        $this->manager->givePermissionTo([
            'leads.view',
            'leads.communicate',
            'leads.convert',
            'members.view',
            'members.edit',
            'members.manage_cards',
            'platform_services.manage_settings',
        ]);

        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Info Desk Officer',
            'email' => 'e2e.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');
        $this->staff->givePermissionTo([
            'leads.view',
            'leads.communicate',
            'members.view',
            'members.edit',
            'members.manage_cards',
        ]);

        $this->chattogramStaff = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Staff',
            'email' => 'e2e.ctg@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->chattogramStaff->assignRole('workspace_manager');

        $this->term = MembershipTerm::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->firstOrFail();
    }

    protected function tearDown(): void
    {
        ImapMailboxAdapter::clearFakeMessages();
        parent::tearDown();
    }

    public function test_scenario_1_bidirectional_imap_sync_and_threaded_profile_display_across_lead_to_member_conversion(): void
    {
        Mail::fake();

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'approved',
            'bkash_txid' => 'E2EMAIL001',
            'first_name' => 'Nusrat',
            'last_name' => 'Jahan',
            'email' => 'applicant@example.com',
            'contact_number' => '+8801711888111',
            'date_of_birth' => '1998-04-20',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => '14',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'International Relations',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subDay(),
        ]);

        // 1. Simulate inbound applicant inquiry email via ImapClientInterface
        ImapMailboxAdapter::fakeMessagesForWorkspace('ws-emk-dhaka', [
            [
                'message_id' => '<e2e-in-001@applicant.example.com>',
                'in_reply_to' => null,
                'references' => null,
                'from_email' => 'applicant@example.com',
                'to_email' => 'infodesk@emkcenter.org',
                'subject' => 'Application Status & Orientation Inquiry',
                'body_text' => 'Assalamu Alaikum, I submitted my bKash payment E2EMAIL001. When is my orientation?',
                'body_html' => '<p>Assalamu Alaikum, I submitted my bKash payment E2EMAIL001. When is my orientation?</p>',
                'received_at' => now()->subHours(2)->toIso8601String(),
                'direction' => 'inbound',
            ],
        ]);

        /** @var MailboxSyncService $syncService */
        $syncService = app(MailboxSyncService::class);
        $syncResult = $syncService->syncWorkspaceMailbox('ws-emk-dhaka');
        $this->assertSame(1, $syncResult['imported']);

        // 2. Staff replies via EmailThreadPanel and inspects LeadProfile HTTP route
        $this->actingAs($this->staff, 'web');

        Livewire::test(EmailThreadPanel::class, [
            'workspace' => 'ws-emk-dhaka',
            'leadId' => $lead->id,
        ])
            ->assertSee('Application Status & Orientation Inquiry')
            ->assertSee('I submitted my bKash payment E2EMAIL001')
            ->set('replyBody', 'Wa Alaikum Assalam Nusrat, your payment is verified and conversion is underway.')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->get("/crm/ws-emk-dhaka/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Official Emails')
            ->assertSee('Application Status & Orientation Inquiry')
            ->assertSee('your payment is verified and conversion is underway.');

        // 3. Convert Lead to Member -> automatically triggers conversion_confirmation & portal_activation workflow rules
        $member = app(LeadConversionService::class)->convert($lead, $this->term->id, $this->manager);

        $this->assertSame('active', $member->status);
        $this->assertNotNull(
            EmailMessage::withoutGlobalScopes()
                ->where('member_id', $member->id)
                ->where('template_key', EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION)
                ->first()
        );
        $this->assertNotNull(
            EmailMessage::withoutGlobalScopes()
                ->where('member_id', $member->id)
                ->where('template_key', EmailTemplate::MILESTONE_PORTAL_ACTIVATION)
                ->first()
        );

        // 4. Mark ID Card printed -> automatically triggers id_card_ready workflow rule
        $card = IdCard::withoutGlobalScopes()->where('member_id', $member->id)->firstOrFail();
        app(IdCardLifecycleService::class)->markPrinted($card, 'BATCH-E2E-08', $this->staff);

        $this->assertNotNull(
            EmailMessage::withoutGlobalScopes()
                ->where('member_id', $member->id)
                ->where('template_key', EmailTemplate::MILESTONE_ID_CARD_READY)
                ->first()
        );

        // 5. Verify full pre-conversion + post-conversion email thread renders on MemberProfile
        $this->get("/crm/ws-emk-dhaka/members/{$member->uuid}")
            ->assertOk()
            ->assertSee('Official Emails')
            ->assertSee('Application Status & Orientation Inquiry')
            ->assertSee('your payment is verified and conversion is underway.');
    }

    public function test_scenario_2_bilingual_template_customization_and_safe_preview(): void
    {
        $template = EmailTemplate::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->where('template_key', EmailTemplate::MILESTONE_RENEWAL_WINDOW)
            ->firstOrFail();

        $component = Livewire::actingAs($this->manager, 'web')
            ->test(EmailTemplateManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('editTemplate', $template->id)
            ->set('name', 'Custom 30-Day Renewal Notice')
            ->set('subject_en', 'Action Required: Renew Membership {{member_id}} before {{expiry_date}}')
            ->set('body_en', '<p>Dear {{member_name}}, renew your {{workspace_name}} membership (Fee: BDT {{amount_bdt}}) before {{expiry_date}}.</p>')
            ->set('subject_bn', 'নবায়ন বিজ্ঞপ্তি: {{member_id}} মেয়াদ {{expiry_date}}')
            ->set('body_bn', '<p>প্রিয় {{member_name}}, {{expiry_date}} তারিখের পূর্বে আপনার সদস্যপদ নবায়ন করুন।</p>')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $component->call('openPreview', $template->id, 'bilingual');
        $preview = $component->get('previewOutput');

        $this->assertStringContainsString('Action Required: Renew Membership EMK-2026-00042', $preview['subject']);
        $this->assertStringContainsString('নবায়ন বিজ্ঞপ্তি: EMK-2026-00042', $preview['subject']);
        $this->assertStringContainsString('Nusrat Jahan', $preview['body_html']);
        $this->assertStringContainsString('প্রিয় Nusrat Jahan', $preview['body_html']);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_EMAIL_TEMPLATE_UPDATED,
            'auditable_id' => $template->id,
        ]);
    }

    public function test_scenario_3_horizon_queued_delivery_retry_deduplication_and_secret_redaction(): void
    {
        Mail::fake();

        /** @var QueuedEmailDispatcher $dispatcher */
        $dispatcher = app(QueuedEmailDispatcher::class);

        // 1. Dispatch lifecycle notification and verify sent status + idempotent deduplication
        $firstMsg = $dispatcher->dispatchTemplate(
            workspaceId: 'ws-emk-dhaka',
            templateKey: EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
            recipientEmail: 'dedup.applicant@example.com',
            variables: [
                'applicant_name' => 'Rafiqul Islam',
                'workspace_name' => 'EMK Center Dhaka',
            ],
            entityKey: 'lead_submitted:501'
        );

        $this->assertSame('sent', $firstMsg->fresh()->status);
        Mail::assertSent(TemplatedLifecycleMail::class, 1);

        // Re-dispatching identical milestone event or re-running job must not send a second email
        $duplicateAttempt = $dispatcher->dispatchTemplate(
            workspaceId: 'ws-emk-dhaka',
            templateKey: EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
            recipientEmail: 'DEDUP.APPLICANT@EXAMPLE.COM',
            variables: [
                'applicant_name' => 'Rafiqul Islam',
                'workspace_name' => 'EMK Center Dhaka',
            ],
            entityKey: 'lead_submitted:501'
        );

        $this->assertSame($firstMsg->id, $duplicateAttempt->id);

        $job = new DispatchOfficialEmailJob($firstMsg->id);
        $job->handle($dispatcher);

        Mail::assertSent(TemplatedLifecycleMail::class, 1);

        // 2. Simulate failed message with embedded SMTP credentials and verify redaction + manual retry
        $rawError = 'SMTP authentication failed for smtp://infodesk:TopSecretSmtpPass88@smtp.office365.com:587 with password= RawSecretToken77';
        $sanitizedReason = $dispatcher->sanitizeFailureMessage($rawError);

        $this->assertStringNotContainsString('TopSecretSmtpPass88', $sanitizedReason);
        $this->assertStringNotContainsString('RawSecretToken77', $sanitizedReason);
        $this->assertStringContainsString('[REDACTED]', $sanitizedReason);

        $thread = EmailThread::withoutGlobalScopes()->where('workspace_id', 'ws-emk-dhaka')->firstOrFail();

        $failedMsg = EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $thread->id,
            'direction' => 'outbound',
            'status' => 'failed',
            'from_email' => 'infodesk@emkcenter.org',
            'to_email' => 'failed.recipient@example.com',
            'subject' => 'Portal Activation Notice',
            'body_html' => '<p>Activate your portal account.</p>',
            'body_text' => 'Activate your portal account.',
            'message_id_header' => '<failed-e2e-001@emkcenter.org>',
            'template_key' => EmailTemplate::MILESTONE_PORTAL_ACTIVATION,
            'deduplication_key' => 'ws-emk-dhaka:portal_activation:failed.recipient@example.com:member:999',
            'failure_reason' => $sanitizedReason,
        ]);

        Livewire::actingAs($this->manager, 'web')
            ->test(EmailDeliveryMonitor::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSee('[REDACTED]')
            ->assertDontSee('TopSecretSmtpPass88')
            ->call('retryMessage', $failedMsg->id)
            ->assertHasNoErrors();

        $this->assertSame('sent', $failedMsg->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_OFFICIAL_EMAIL_RETRIED,
            'auditable_id' => $failedMsg->id,
        ]);
    }

    public function test_scenario_4_automated_workflow_rules_and_expiry_renewal_scheduler(): void
    {
        Mail::fake();

        // Customize renewal_window template first
        EmailTemplate::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->where('template_key', EmailTemplate::MILESTONE_RENEWAL_WINDOW)
            ->update([
                'subject_en' => 'Custom E2E Renewal Reminder for {{member_id}}',
            ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'approved',
            'bkash_txid' => 'E2EMAIL004',
            'first_name' => 'Tahmid',
            'last_name' => 'Chowdhury',
            'email' => 'tahmid.member@example.com',
            'contact_number' => '+8801711888444',
            'date_of_birth' => '1997-08-12',
            'age_at_submission' => 29,
            'sex' => 'male',
            'address_house_no' => '33',
            'address_area' => 'Uttara',
            'address_zip_code' => '1230',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'entrepreneur',
            'institution_study' => 'IBA DU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Business Administration',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subYear(),
        ]);

        $member = app(LeadConversionService::class)->convert($lead, $this->term->id, $this->manager);

        // 1. Set expiry_date to 20 days from today and run crm:process-membership-milestones
        $member->update([
            'expiry_date' => now()->addDays(20)->toDateString(),
        ]);

        Artisan::call('crm:process-membership-milestones', ['--workspace' => 'ws-emk-dhaka']);

        $renewalEmail = EmailMessage::withoutGlobalScopes()
            ->where('member_id', $member->id)
            ->where('template_key', EmailTemplate::MILESTONE_RENEWAL_WINDOW)
            ->first();

        $this->assertNotNull($renewalEmail);
        $this->assertStringContainsString("Custom E2E Renewal Reminder for {$member->member_id}", (string) $renewalEmail->subject);
        $this->assertNotNull($renewalEmail->email_thread_id);

        // 2. Move expiry_date to yesterday and run crm:process-membership-milestones
        $member->update([
            'expiry_date' => now()->subDay()->toDateString(),
        ]);

        Artisan::call('crm:process-membership-milestones', ['--workspace' => 'ws-emk-dhaka']);

        $this->assertSame('inactive', $member->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'event_type' => AuditLog::EVENT_MEMBER_STATUS_CHANGED,
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
        ]);

        $expiryEmail = EmailMessage::withoutGlobalScopes()
            ->where('member_id', $member->id)
            ->where('template_key', EmailTemplate::MILESTONE_EXPIRY)
            ->first();

        $this->assertNotNull($expiryEmail);
    }

    public function test_scenario_5_cross_workspace_tenancy_isolation_returns_403(): void
    {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'under_review',
            'bkash_txid' => 'E2EMAIL005',
            'first_name' => 'Isolated',
            'last_name' => 'Applicant',
            'email' => 'isolated.applicant@example.com',
            'contact_number' => '+8801711888555',
            'date_of_birth' => '1999-01-01',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => '9',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'BUET',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'CSE',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->chattogramStaff, 'web');

        // Route-level 403 checks
        $this->get('/crm/ws-emk-dhaka/settings/email-templates')->assertStatus(403);
        $this->get('/crm/ws-emk-dhaka/settings/email-delivery')->assertStatus(403);
        $this->get('/crm/ws-emk-dhaka/settings/workflow-rules')->assertStatus(403);

        // Livewire component-level 403 checks
        Livewire::test(EmailTemplateManager::class, ['workspace' => 'ws-emk-dhaka'])->assertForbidden();
        Livewire::test(EmailDeliveryMonitor::class, ['workspace' => 'ws-emk-dhaka'])->assertForbidden();
        Livewire::test(WorkflowRuleManager::class, ['workspace' => 'ws-emk-dhaka'])->assertForbidden();
        Livewire::test(EmailThreadPanel::class, ['workspace' => 'ws-emk-dhaka', 'leadId' => $lead->id])->assertForbidden();
    }
}
