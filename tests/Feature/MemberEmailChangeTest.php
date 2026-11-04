<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Mail\ManagerEmailChangeAlertMail;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\MemberChangeRequestService;
use App\Livewire\Portal\Modals\SubmitEmailChangeModal;
use App\Models\MemberUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MemberEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createMemberFixture(
        string $email = 'member.test@example.com',
        string $memberId = 'MEM-DHK-2026-0001',
        string $workspaceId = 'ws-emk-dhaka'
    ): array {
        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => $workspaceId, 'code' => 'REG-1YR'],
            [
                'name' => '1 Year Regular Membership',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ]
        );

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXID' . strtoupper(Str::random(6)),
            'first_name' => 'John',
            'middle_name' => 'Q',
            'last_name' => 'Doe',
            'email' => $email,
            'contact_number' => '+8801712345678',
            'date_of_birth' => '1995-08-20',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => '12/A',
            'address_road_no' => 'Road 5',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka College',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'Science',
            'submitted_at' => now(),
        ]);

        $memberUser = MemberUser::create([
            'workspace_id' => $workspaceId,
            'name' => 'John Doe',
            'email' => $email,
            'password' => Hash::make('MemberPass123!'),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $memberUser->assignRole('member');

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $memberId,
            'workspace_id' => $workspaceId,
            'lead_id' => $lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'John',
            'middle_name' => 'Q',
            'last_name' => 'Doe',
            'email' => $email,
            'contact_number' => '+8801712345678',
            'date_of_birth' => '1995-08-20',
            'sex' => 'male',
            'address_house_no' => '12/A',
            'address_road_no' => 'Road 5',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka College',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'Science',
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        return compact('member', 'memberUser', 'lead', 'term');
    }

    protected function createStaffUser(
        string $role = 'workspace_manager',
        string $workspaceId = 'ws-emk-dhaka',
        string $email = 'staff.manager@example.com'
    ): User {
        $user = User::create([
            'workspace_id' => $workspaceId,
            'name' => 'Staff User',
            'email' => $email,
            'password' => bcrypt('Password123!'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_member_can_submit_valid_email_change_request_with_justification(): void
    {
        Mail::fake();

        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $newEmail = 'new.email@example.com';
        $justification = 'Migrating to my primary official university email address.';

        $service = app(MemberChangeRequestService::class);
        $request = $service->submitEmailChangeRequest($member, $newEmail, $justification);

        $this->assertInstanceOf(MemberChangeRequest::class, $request);
        $this->assertEquals(MemberChangeRequest::TYPE_EMAIL_CHANGE, $request->request_type);
        $this->assertEquals(MemberChangeRequest::STATUS_PENDING, $request->status);
        $this->assertEquals($member->id, $request->member_id);
        $this->assertEquals(['email' => $member->email], $request->old_payload);
        $this->assertEquals(['email' => $newEmail], $request->new_payload);
        $this->assertEquals($justification, $request->justification);

        // Member profile must NOT be mutated upon submission
        $freshMember = $member->fresh();
        $this->assertEquals('member.test@example.com', $freshMember->email);

        // Audit log entry asserted
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditLog::EVENT_MEMBER_EMAIL_CHANGE_REQUESTED,
            'auditable_type' => MemberChangeRequest::class,
            'auditable_id' => $request->id,
            'workspace_id' => $member->workspace_id,
        ]);
    }

    public function test_submitting_already_registered_email_in_members_table_throws_exception(): void
    {
        $fixture1 = $this->createMemberFixture('first@example.com', 'MEM-DHK-2026-0001');
        $this->createMemberFixture('second@example.com', 'MEM-DHK-2026-0002');

        $service = app(MemberChangeRequestService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The requested email address is already in use by another account.');

        $service->submitEmailChangeRequest(
            $fixture1['member'],
            'second@example.com',
            'Attempting to claim an already existing member email address.'
        );
    }

    public function test_submitting_already_registered_email_in_users_table_throws_exception(): void
    {
        $fixture = $this->createMemberFixture('member@example.com', 'MEM-DHK-2026-0001');
        $this->createStaffUser('delegated_staff', 'ws-emk-dhaka', 'existing.staff@example.com');

        $service = app(MemberChangeRequestService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The requested email address is already in use by another account.');

        $service->submitEmailChangeRequest(
            $fixture['member'],
            'existing.staff@example.com',
            'Attempting to claim an email registered in users table.'
        );
    }

    public function test_submitting_duplicate_pending_email_change_request_is_blocked(): void
    {
        Mail::fake();

        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];

        $service = app(MemberChangeRequestService::class);
        $service->submitEmailChangeRequest($member, 'first.new@example.com', 'Valid justification 10 characters.');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('An email change request is already pending review for this member.');

        $service->submitEmailChangeRequest($member, 'second.new@example.com', 'Another valid justification 10.');
    }

    public function test_submitting_invalid_email_format_or_identical_email_throws_exception(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $service = app(MemberChangeRequestService::class);

        try {
            $service->submitEmailChangeRequest($member, 'not-an-email', 'Valid justification string.');
            $this->fail('Expected DomainException for invalid email format.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('format is invalid', $e->getMessage());
        }

        try {
            $service->submitEmailChangeRequest($member, 'member.test@example.com', 'Valid justification string.');
            $this->fail('Expected DomainException for identical email.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('must differ from your current', $e->getMessage());
        }
    }

    public function test_submitting_short_justification_throws_exception(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $service = app(MemberChangeRequestService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A valid justification of at least 10 characters is required.');

        $service->submitEmailChangeRequest($member, 'valid.new@example.com', 'Too short');
    }

    public function test_manager_email_change_alert_mail_is_queued_upon_submission(): void
    {
        Mail::fake();

        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka', 'manager.dhaka@example.com');
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $newEmail = 'alert.test@example.com';
        $justification = 'Need to update to my new personal email address.';

        $service = app(MemberChangeRequestService::class);
        $service->submitEmailChangeRequest($member, $newEmail, $justification);

        Mail::assertQueued(ManagerEmailChangeAlertMail::class, function (ManagerEmailChangeAlertMail $mail) use ($member, $newEmail, $justification) {
            $this->assertEquals($member->id, $mail->member->id);
            $this->assertEquals($newEmail, $mail->newEmail);
            $this->assertEquals($justification, $mail->justification);

            $envelope = $mail->envelope();
            $this->assertStringContainsString($member->member_id, $envelope->subject);

            return true;
        });
    }

    public function test_portal_email_change_modal_renders_and_submits_via_livewire(): void
    {
        Mail::fake();

        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];

        Livewire::actingAs($memberUser, 'member')
            ->test(SubmitEmailChangeModal::class, ['member' => $member])
            ->assertSet('isOpen', false)
            ->dispatch('openSubmitEmailChangeModal')
            ->assertSet('isOpen', true)
            ->set('new_email', 'brand.new.email@example.com')
            ->set('new_email_confirmation', 'brand.new.email@example.com')
            ->set('justification', 'Requested email change justification with adequate length.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('isOpen', false)
            ->assertSet('hasPendingRequest', true)
            ->assertDispatched('email-change-submitted');

        $this->assertDatabaseHas('member_change_requests', [
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
            'status' => MemberChangeRequest::STATUS_PENDING,
        ]);
    }

    public function test_portal_email_change_modal_blocks_submission_when_pending_request_exists(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];

        // Pre-create pending email change request
        MemberChangeRequest::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['email' => $member->email],
            'new_payload' => ['email' => 'pending.one@example.com'],
            'justification' => 'Already pending request justification.',
        ]);

        Livewire::actingAs($memberUser, 'member')
            ->test(SubmitEmailChangeModal::class, ['member' => $member])
            ->assertSet('hasPendingRequest', true)
            ->dispatch('openSubmitEmailChangeModal')
            ->set('new_email', 'another@example.com')
            ->set('new_email_confirmation', 'another@example.com')
            ->set('justification', 'Another valid justification for review.')
            ->call('submit')
            ->assertSet('submissionError', 'An email change request is already pending review for this member.');
    }
}
