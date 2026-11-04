<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Mail\Services\ImapMailboxAdapter;
use App\Domain\Mail\Services\MailboxSyncService;
use App\Domain\Members\Models\Member;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailboxSyncAndThreadTracerTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staff;
    protected User $otherWorkspaceStaff;
    protected Lead $lead;
    protected Member $member;

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
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'manager.mail@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Info Desk Officer',
            'email' => 'infodesk.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');
        $this->staff->givePermissionTo(['leads.view', 'leads.communicate', 'members.view', 'members.edit']);

        $this->otherWorkspaceStaff = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Staff',
            'email' => 'ctg.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->otherWorkspaceStaff->assignRole('delegated_staff');
        $this->otherWorkspaceStaff->givePermissionTo(['leads.view', 'leads.communicate', 'members.view', 'members.edit']);

        $this->lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'under_review',
            'bkash_txid' => 'MAILTX0001',
            'first_name' => 'Tariq',
            'last_name' => 'Rahman',
            'email' => 'tariq.applicant@example.com',
            'contact_number' => '+8801711000111',
            'date_of_birth' => '1999-05-15',
            'age_at_submission' => 27,
            'sex' => 'male',
            'address_house_no' => '10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student_ug',
            'institution_study' => 'BUET',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'Engineering',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $convertedLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'MAILTX0002',
            'first_name' => 'Samira',
            'last_name' => 'Khan',
            'email' => 'samira.member@example.com',
            'contact_number' => '+8801711000222',
            'date_of_birth' => '1995-03-10',
            'age_at_submission' => 31,
            'sex' => 'female',
            'address_house_no' => '22',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'service',
            'institution_study' => 'NSU',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'Economics',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now(),
        ]);

        $portalUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Samira Khan',
            'email' => 'samira.member@example.com',
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        $term = MembershipTerm::first();

        $this->member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-00901',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $convertedLead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Samira',
            'last_name' => 'Khan',
            'email' => 'samira.member@example.com',
            'contact_number' => '+8801711000222',
            'date_of_birth' => '1995-03-10',
            'sex' => 'female',
            'address_house_no' => '22',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'service',
            'institution_study' => 'NSU',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'Economics',
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $convertedLead->update(['converted_member_id' => $this->member->id]);
    }

    protected function tearDown(): void
    {
        ImapMailboxAdapter::clearFakeMessages();
        parent::tearDown();
    }

    public function test_migration_creates_phase_8_email_and_workflow_tables(): void
    {
        $this->assertTrue(Schema::hasTable('email_threads'));
        $this->assertTrue(Schema::hasTable('email_messages'));
        $this->assertTrue(Schema::hasTable('email_templates'));
        $this->assertTrue(Schema::hasTable('workflow_rules'));
    }

    public function test_bidirectional_imap_sync_matches_leads_and_members_threads_replies_and_deduplicates(): void
    {
        ImapMailboxAdapter::fakeMessagesForWorkspace('ws-emk-dhaka', [
            [
                'message_id' => '<msg-001@applicant.test>',
                'in_reply_to' => null,
                'references' => null,
                'from_email' => 'tariq.applicant@example.com',
                'to_email' => 'infodesk@emkcenter.org',
                'subject' => 'Question about bKash verification',
                'body_text' => 'Hello, I submitted my bKash transaction ID yesterday.',
                'body_html' => '<p onclick="evil()">Hello, I submitted my bKash transaction ID yesterday.</p><script>alert(1)</script>',
                'received_at' => '2026-11-04T10:00:00Z',
                'direction' => 'inbound',
            ],
            [
                'message_id' => '<msg-002@emkcenter.org>',
                'in_reply_to' => '<msg-001@applicant.test>',
                'references' => '<msg-001@applicant.test>',
                'from_email' => 'infodesk@emkcenter.org',
                'to_email' => 'tariq.applicant@example.com',
                'subject' => 'Re: Question about bKash verification',
                'body_text' => 'Thank you Tariq, our officer is verifying it now.',
                'body_html' => '<p>Thank you Tariq, our officer is verifying it now.</p>',
                'received_at' => '2026-11-04T10:15:00Z',
                'direction' => 'outbound',
            ],
            [
                'message_id' => '<msg-003@member.test>',
                'in_reply_to' => null,
                'references' => null,
                'from_email' => 'samira.member@example.com',
                'to_email' => 'infodesk@emkcenter.org',
                'subject' => 'ID Card pickup schedule',
                'body_text' => 'Can I pick up my card on Thursday?',
                'body_html' => '<p>Can I pick up my card on Thursday?</p>',
                'received_at' => '2026-11-04T11:00:00Z',
                'direction' => 'inbound',
            ],
            [
                'message_id' => '<msg-999@spam.test>',
                'in_reply_to' => null,
                'references' => null,
                'from_email' => 'random.stranger@unknown.test',
                'to_email' => 'infodesk@emkcenter.org',
                'subject' => 'Unrelated marketing spam',
                'body_text' => 'Buy cheap SEO services',
                'body_html' => '<p>Buy cheap SEO services</p>',
                'received_at' => '2026-11-04T11:05:00Z',
                'direction' => 'inbound',
            ],
        ]);

        /** @var MailboxSyncService $syncService */
        $syncService = app(MailboxSyncService::class);
        $firstRun = $syncService->syncWorkspaceMailbox('ws-emk-dhaka');

        $this->assertSame(3, $firstRun['imported']);
        $this->assertSame(0, $firstRun['skipped_duplicates']);
        $this->assertSame(1, $firstRun['unmatched']);

        // Verify Lead thread grouped 2 messages via In-Reply-To
        $leadThread = EmailThread::where('workspace_id', 'ws-emk-dhaka')
            ->where('lead_id', $this->lead->id)
            ->first();

        $this->assertNotNull($leadThread);
        $this->assertSame(2, $leadThread->message_count);
        $this->assertCount(2, $leadThread->messages);

        // Verify HTML sanitization stripped <script> and onclick attribute
        $firstMessage = $leadThread->messages->first();
        $this->assertStringNotContainsString('<script>', (string) $firstMessage->body_html);
        $this->assertStringNotContainsString('onclick=', (string) $firstMessage->body_html);

        // Verify Member thread linked both member_id and originating lead_id
        $memberThread = EmailThread::where('workspace_id', 'ws-emk-dhaka')
            ->where('member_id', $this->member->id)
            ->first();

        $this->assertNotNull($memberThread);
        $this->assertSame($this->member->lead_id, $memberThread->lead_id);
        $this->assertSame(1, $memberThread->message_count);

        // Verify idempotency on second run via artisan command
        $this->artisan('crm:mailbox-sync', ['--workspace' => 'ws-emk-dhaka'])
            ->assertExitCode(0);

        $this->assertSame(3, EmailMessage::where('workspace_id', 'ws-emk-dhaka')->count());
    }

    public function test_outbound_reply_dispatches_email_persists_message_and_records_audit_log(): void
    {
        Mail::fake();

        $thread = EmailThread::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->lead->id,
            'participant_email' => $this->lead->email,
            'subject' => 'Document clarification',
            'thread_reference' => '<initial-doc@applicant.test>',
            'last_message_at' => now()->subHour(),
            'message_count' => 1,
        ]);

        EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $thread->id,
            'lead_id' => $this->lead->id,
            'direction' => 'inbound',
            'status' => 'received',
            'from_email' => $this->lead->email,
            'to_email' => 'infodesk@emkcenter.org',
            'subject' => 'Document clarification',
            'body_text' => 'Here is my question regarding NID upload.',
            'message_id_header' => '<initial-doc@applicant.test>',
            'received_at' => now()->subHour(),
        ]);

        /** @var MailboxSyncService $syncService */
        $syncService = app(MailboxSyncService::class);
        $replyMessage = $syncService->sendAndRecordReply(
            $thread,
            $this->staff,
            'Please upload a clear color scan of your original NID.',
        );

        $this->assertSame('outbound', $replyMessage->direction);
        $this->assertSame('sent', $replyMessage->status);
        $this->assertSame('<initial-doc@applicant.test>', $replyMessage->in_reply_to_header);
        $this->assertSame('Re: Document clarification', $replyMessage->subject);
        $this->assertSame(2, $thread->fresh()->message_count);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->staff->id,
            'event_type' => AuditLog::EVENT_OFFICIAL_EMAIL_REPLY_SENT,
            'auditable_id' => $replyMessage->id,
        ]);
    }
}
