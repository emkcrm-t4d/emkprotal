<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Mail\LeadRejectionMail;
use App\Domain\Leads\Mail\MissingItemsNotificationMail;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadVerification;
use App\Domain\Leads\Services\LeadCommunicationService;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class LeadCommunicationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LeadCommunicationService $service;
    protected User $manager;
    protected User $staff;
    protected User $unauthorizedUser;
    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->service = app(LeadCommunicationService::class);

        // 1. Workspace Manager (has leads.communicate, leads.reject, etc.)
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager User',
            'email' => 'mgr.comm@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Delegated Staff (has leads.communicate; NO leads.reject)
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff User',
            'email' => 'staff.comm@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // 3. User with no permissions
        $this->unauthorizedUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Unauthorized User',
            'email' => 'noauth.comm@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);

        // 4. Test Lead
        $this->lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'under_review',
            'bkash_txid' => 'COMMTEST01',
            'first_name' => 'Sarah',
            'last_name' => 'Applicant',
            'email' => 'sarah.applicant@example.com',
            'contact_number' => '+8801712345099',
            'date_of_birth' => '2000-01-15',
            'age_at_submission' => 26,
            'sex' => 'female',
            'address_house_no' => '12',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'Economics',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    public function test_send_missing_items_notification_dispatches_mail_and_transitions_status(): void
    {
        Mail::fake();

        $missingItems = [
            'National ID front side unreadable/cropped',
            'Background is textured/coloured (must be solid white)',
        ];
        $notes = 'Please re-upload a clear scan of your National ID and a studio photo with a solid white background.';

        $this->service->sendMissingItemsNotification(
            $this->lead,
            $this->staff,
            $missingItems,
            $notes
        );

        // Assert Mailable queued
        Mail::assertQueued(MissingItemsNotificationMail::class, function (MissingItemsNotificationMail $mail) use ($missingItems, $notes) {
            return $mail->hasTo('sarah.applicant@example.com')
                && $mail->missingItems === $missingItems
                && $mail->customInstructions === $notes;
        });

        // Assert Lead Status transition
        $freshLead = $this->lead->fresh();
        $this->assertEquals('missing_items', $freshLead->status);
        $this->assertEquals('missing_items', $freshLead->document_verification_status);
        $this->assertEquals('reupload_requested', $freshLead->photo_verification_status);

        // Assert Audit Log entry
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->staff->id,
            'event_type' => AuditLog::EVENT_LEAD_MISSING_ITEMS_SENT,
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
        ]);
    }

    public function test_reject_lead_validates_taxonomy_dispatches_mail_and_records_audit(): void
    {
        Mail::fake();

        $reasonCode = 'DOCUMENT_UNREADABLE';
        $internalNotes = 'Scanned file is blurry and illegible; name cannot be verified.';

        $this->service->rejectLead(
            $this->lead,
            $this->manager,
            $reasonCode,
            $internalNotes
        );

        // Assert Mailable queued
        Mail::assertQueued(LeadRejectionMail::class, function (LeadRejectionMail $mail) use ($reasonCode) {
            return $mail->hasTo('sarah.applicant@example.com')
                && $mail->rejectionReasonCode === $reasonCode
                && str_contains($mail->applicantFacingMessage, 'unreadable or blurry');
        });

        // Assert Lead status and stream status updated
        $freshLead = $this->lead->fresh();
        $this->assertEquals('rejected', $freshLead->status);
        $this->assertEquals('rejected', $freshLead->document_verification_status);

        // Assert LeadVerification record created
        $this->assertDatabaseHas('lead_verifications', [
            'lead_id' => $this->lead->id,
            'stream' => 'document',
            'status' => 'rejected',
            'rejection_reason_code' => $reasonCode,
            'verified_by_user_id' => $this->manager->id,
        ]);

        // Assert Audit Log entry
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_LEAD_REJECTED,
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
        ]);
    }

    public function test_reject_lead_throws_exception_for_invalid_reason_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid rejection reason code: INVALID_CODE_123');

        $this->service->rejectLead(
            $this->lead,
            $this->manager,
            'INVALID_CODE_123'
        );
    }

    public function test_block_applicant_email_updates_status_and_records_audit(): void
    {
        $justification = 'Repeat fraudulent submission with stolen identities and fake bKash transaction IDs.';

        $this->service->blockApplicantEmail(
            $this->lead,
            $this->manager,
            $justification
        );

        $freshLead = $this->lead->fresh();
        $this->assertEquals('blocked', $freshLead->status);

        // Assert Audit Log entry
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_LEAD_BLOCKED,
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
            'reason' => $justification,
        ]);
    }

    public function test_unauthorized_staff_cannot_reject_or_block_leads(): void
    {
        // 1. Staff cannot reject lead
        try {
            $this->service->rejectLead($this->lead, $this->staff, 'DOCUMENT_UNREADABLE');
            $this->fail('Expected UnauthorizedVerificationException for staff rejecting lead');
        } catch (UnauthorizedVerificationException $e) {
            $this->assertStringContainsString('leads.reject', $e->getMessage());
        }

        // 2. Staff cannot block lead email
        try {
            $this->service->blockApplicantEmail($this->lead, $this->staff, 'Attempted block');
            $this->fail('Expected UnauthorizedVerificationException for staff blocking email');
        } catch (UnauthorizedVerificationException $e) {
            $this->assertStringContainsString('leads.reject', $e->getMessage());
        }

        // 3. Unauthorized user cannot send missing items
        try {
            $this->service->sendMissingItemsNotification($this->lead, $this->unauthorizedUser, ['Item']);
            $this->fail('Expected UnauthorizedVerificationException for unauthorized user sending missing items');
        } catch (UnauthorizedVerificationException $e) {
            $this->assertStringContainsString('leads.communicate', $e->getMessage());
        }
    }
}
