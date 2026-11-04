<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Workspaces\Models\Workspace;
use App\Jobs\DispatchOfficialEmailJob;
use App\Livewire\Mail\EmailDeliveryMonitor;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EmailDeliveryMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $unauthorizedStaff;
    protected User $otherWorkspaceManager;
    protected EmailThread $thread;
    protected EmailMessage $sentMessage;
    protected EmailMessage $failedMessage;
    protected EmailMessage $queuedMessage;

    protected function setUp(): void
    {
        parent::setUp();
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
            'name' => 'Dhaka Workspace Manager',
            'email' => 'manager.delivery@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        $this->unauthorizedStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Basic Staff',
            'email' => 'staff.basic@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->unauthorizedStaff->assignRole('delegated_staff');
        $this->unauthorizedStaff->givePermissionTo(['leads.view']);

        $this->otherWorkspaceManager = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Manager',
            'email' => 'manager.ctg@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->otherWorkspaceManager->assignRole('workspace_manager');

        $this->thread = EmailThread::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'participant_email' => 'applicant.monitor@example.com',
            'subject' => 'Application Received — EMK Center Dhaka Membership',
            'last_message_at' => now(),
            'message_count' => 3,
            'is_resolved' => false,
        ]);

        $this->sentMessage = EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $this->thread->id,
            'direction' => 'outbound',
            'status' => 'sent',
            'from_email' => 'infodesk@emkcenter.org',
            'to_email' => 'sent.recipient@example.com',
            'subject' => 'Application Received — EMK Center Dhaka Membership',
            'body_html' => '<p>Sent body</p>',
            'body_text' => 'Sent body',
            'message_id_header' => '<msg-sent-1@emkcenter.org>',
            'template_key' => EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
            'milestone_type' => EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
            'deduplication_key' => 'ws-emk-dhaka:application_receipt:sent.recipient@example.com:lead:1',
            'sent_at' => now(),
        ]);

        $this->failedMessage = EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $this->thread->id,
            'direction' => 'outbound',
            'status' => 'failed',
            'from_email' => 'infodesk@emkcenter.org',
            'to_email' => 'failed.recipient@example.com',
            'subject' => 'Action Required: Additional Information Needed',
            'body_html' => '<p>Failed body</p>',
            'body_text' => 'Failed body',
            'message_id_header' => '<msg-failed-2@emkcenter.org>',
            'template_key' => EmailTemplate::MILESTONE_MISSING_ITEMS,
            'milestone_type' => EmailTemplate::MILESTONE_MISSING_ITEMS,
            'deduplication_key' => 'ws-emk-dhaka:missing_items:failed.recipient@example.com:lead:2',
            'failure_reason' => 'SMTP timeout on smtp://infodesk:[REDACTED]@mail.emkcenter.org',
        ]);

        $this->queuedMessage = EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $this->thread->id,
            'direction' => 'outbound',
            'status' => 'queued',
            'from_email' => 'infodesk@emkcenter.org',
            'to_email' => 'queued.recipient@example.com',
            'subject' => 'Welcome to EMK Center Dhaka',
            'body_html' => '<p>Queued body</p>',
            'body_text' => 'Queued body',
            'message_id_header' => '<msg-queued-3@emkcenter.org>',
            'template_key' => EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION,
            'milestone_type' => EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION,
            'deduplication_key' => 'ws-emk-dhaka:conversion_confirmation:queued.recipient@example.com:member:3',
        ]);
    }

    public function test_workspace_manager_can_view_email_delivery_monitor_and_filter_by_status_and_search(): void
    {
        $response = $this->actingAs($this->manager, 'web')
            ->get('/crm/ws-emk-dhaka/settings/email-delivery');

        $response->assertStatus(200);
        $response->assertSee('Horizon Email Delivery Monitor');
        $response->assertSee('sent.recipient@example.com');
        $response->assertSee('failed.recipient@example.com');
        $response->assertSee('queued.recipient@example.com');

        Livewire::actingAs($this->manager, 'web')
            ->test(EmailDeliveryMonitor::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('statusFilter', 'failed')
            ->assertSee('failed.recipient@example.com')
            ->assertDontSee('sent.recipient@example.com')
            ->assertDontSee('queued.recipient@example.com')
            ->set('statusFilter', 'sent')
            ->assertSee('sent.recipient@example.com')
            ->assertDontSee('failed.recipient@example.com')
            ->set('statusFilter', '')
            ->set('search', 'queued.recipient')
            ->assertSee('queued.recipient@example.com')
            ->assertDontSee('sent.recipient@example.com');
    }

    public function test_retrying_failed_message_requeues_dispatches_horizon_job_and_logs_audit_event(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        Livewire::actingAs($this->manager, 'web')
            ->test(EmailDeliveryMonitor::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('retryMessage', $this->failedMessage->id)
            ->assertSee('Failed email re-queued for delivery via Horizon.');

        $reloaded = EmailMessage::withoutGlobalScopes()->findOrFail($this->failedMessage->id);
        $this->assertSame('queued', $reloaded->status);
        $this->assertNull($reloaded->failure_reason);

        Bus::assertDispatched(DispatchOfficialEmailJob::class, function (DispatchOfficialEmailJob $job): bool {
            return $job->emailMessageId === $this->failedMessage->id;
        });

        $audit = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_OFFICIAL_EMAIL_RETRIED)
            ->where('auditable_id', $this->failedMessage->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->manager->id, $audit->actor_id);
        $this->assertSame('ws-emk-dhaka', $audit->workspace_id);
    }

    public function test_retrying_already_sent_message_is_refused_and_does_not_dispatch_duplicate_job(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        Livewire::actingAs($this->manager, 'web')
            ->test(EmailDeliveryMonitor::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('retryMessage', $this->sentMessage->id)
            ->assertSee('Message was already sent; duplicate retry skipped.');

        Bus::assertNotDispatched(DispatchOfficialEmailJob::class);

        $reloaded = EmailMessage::withoutGlobalScopes()->findOrFail($this->sentMessage->id);
        $this->assertSame('sent', $reloaded->status);
    }

    public function test_unauthorized_staff_and_cross_workspace_managers_receive_403(): void
    {
        $this->actingAs($this->unauthorizedStaff, 'web')
            ->get('/crm/ws-emk-dhaka/settings/email-delivery')
            ->assertStatus(403);

        $this->actingAs($this->otherWorkspaceManager, 'web')
            ->get('/crm/ws-emk-dhaka/settings/email-delivery')
            ->assertStatus(403);
    }
}
