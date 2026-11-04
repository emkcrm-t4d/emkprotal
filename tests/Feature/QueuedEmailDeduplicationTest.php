<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Mailables\TemplatedLifecycleMail;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Mail\Services\QueuedEmailDispatcher;
use App\Jobs\DispatchOfficialEmailJob;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QueuedEmailDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dispatch_template_prevents_duplicate_email_messages_and_duplicate_jobs_for_identical_milestone_events(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        /** @var QueuedEmailDispatcher $dispatcher */
        $dispatcher = app(QueuedEmailDispatcher::class);

        $first = $dispatcher->dispatchTemplate(
            workspaceId: 'ws-emk-dhaka',
            templateKey: EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
            recipientEmail: 'applicant.dedup@example.com',
            variables: [
                'applicant_name' => 'Farhana Karim',
                'workspace_name' => 'EMK Center Dhaka',
                'amount_bdt' => '500.00',
            ],
            entityKey: 'lead:101',
            locale: 'bilingual'
        );

        $second = $dispatcher->dispatchTemplate(
            workspaceId: 'ws-emk-dhaka',
            templateKey: EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
            recipientEmail: 'APPLICANT.DEDUP@EXAMPLE.COM ',
            variables: [
                'applicant_name' => 'Farhana Karim',
                'workspace_name' => 'EMK Center Dhaka',
                'amount_bdt' => '500.00',
            ],
            entityKey: 'lead:101',
            locale: 'bilingual'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame('queued', $first->status);
        $this->assertSame(
            'ws-emk-dhaka:application_receipt:applicant.dedup@example.com:lead:101',
            $first->deduplication_key
        );

        $this->assertSame(1, EmailMessage::withoutGlobalScopes()->count());
        $this->assertSame(1, EmailThread::withoutGlobalScopes()->count());

        Bus::assertDispatchedTimes(DispatchOfficialEmailJob::class, 1);
    }

    public function test_executing_dispatch_official_email_job_twice_on_same_message_is_idempotent_and_sends_only_once(): void
    {
        Mail::fake();

        /** @var QueuedEmailDispatcher $dispatcher */
        $dispatcher = app(QueuedEmailDispatcher::class);

        // Under testing queue=sync, dispatchTemplate immediately executes DispatchOfficialEmailJob once
        $message = $dispatcher->dispatchTemplate(
            workspaceId: 'ws-emk-dhaka',
            templateKey: EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION,
            recipientEmail: 'member.retry@example.com',
            variables: [
                'member_name' => 'Tanvir Ahmed',
                'member_id' => 'EMK-2026-00055',
                'workspace_name' => 'EMK Center Dhaka',
                'invoice_number' => 'INV-2026-00055',
                'amount_bdt' => '500.00',
                'expiry_date' => '04 Nov 2027',
            ],
            entityKey: 'member:55',
            locale: 'bilingual'
        );

        $fresh = EmailMessage::withoutGlobalScopes()->findOrFail($message->id);
        $this->assertSame('sent', $fresh->status);
        $this->assertNotNull($fresh->sent_at);

        Mail::assertSent(TemplatedLifecycleMail::class, 1);

        // Manually invoke the job handler a second time simulating a Horizon worker retry
        $job = new DispatchOfficialEmailJob($fresh->id);
        $job->handle($dispatcher);

        // Still only 1 email sent and 1 audit log entry for official_email_dispatched
        Mail::assertSent(TemplatedLifecycleMail::class, 1);
        $this->assertSame(
            1,
            AuditLog::withoutGlobalScopes()
                ->where('event_type', AuditLog::EVENT_OFFICIAL_EMAIL_DISPATCHED)
                ->where('auditable_id', $fresh->id)
                ->count()
        );

        // Also verify Horizon job retry and uniqueness configuration
        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120, 300], $job->backoff);
        $this->assertSame('email-msg-' . $fresh->id, $job->uniqueId());
    }

    public function test_transport_exception_marks_message_failed_and_redacts_smtp_credentials_in_db_and_audit_log(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        /** @var QueuedEmailDispatcher $dispatcher */
        $dispatcher = app(QueuedEmailDispatcher::class);

        $message = $dispatcher->dispatchTemplate(
            workspaceId: 'ws-emk-dhaka',
            templateKey: EmailTemplate::MILESTONE_PORTAL_ACTIVATION,
            recipientEmail: 'member.fail@example.com',
            variables: [
                'member_name' => 'Rashed Khan',
                'member_id' => 'EMK-2026-00088',
                'workspace_name' => 'EMK Center Dhaka',
                'action_url' => 'https://hub.emkcenter.org/portal/login',
            ],
            entityKey: 'member:88',
            locale: 'en'
        );

        Mail::shouldReceive('to')
            ->once()
            ->with('member.fail@example.com')
            ->andThrow(new \RuntimeException(
                'Connection refused on smtp://infodesk:SuperSecretPass123@mail.emkcenter.org:587 with password=MyRawSmtpSecret999'
            ));

        $job = new DispatchOfficialEmailJob($message->id);

        $exceptionThrown = false;
        try {
            $job->handle($dispatcher);
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, 'Expected DispatchOfficialEmailJob to rethrow transport exception for Horizon retry tracking.');

        $failedMessage = EmailMessage::withoutGlobalScopes()->findOrFail($message->id);
        $this->assertSame('failed', $failedMessage->status);
        $this->assertNotNull($failedMessage->failure_reason);
        $this->assertStringNotContainsString('SuperSecretPass123', (string) $failedMessage->failure_reason);
        $this->assertStringNotContainsString('MyRawSmtpSecret999', (string) $failedMessage->failure_reason);
        $this->assertStringContainsString('[REDACTED]', (string) $failedMessage->failure_reason);

        $auditLog = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_OFFICIAL_EMAIL_FAILED)
            ->where('auditable_id', $message->id)
            ->first();

        $this->assertNotNull($auditLog);
        $auditJson = json_encode($auditLog->new_values);
        $this->assertStringNotContainsString('SuperSecretPass123', (string) $auditJson);
        $this->assertStringNotContainsString('MyRawSmtpSecret999', (string) $auditJson);
        $this->assertStringContainsString('[REDACTED]', (string) $auditJson);
    }
}
