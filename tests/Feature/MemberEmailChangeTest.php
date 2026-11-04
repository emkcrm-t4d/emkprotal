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
use App\Livewire\Members\MemberChangeRequestDirectory;
use App\Livewire\Portal\Modals\SubmitEmailChangeModal;
use App\Livewire\Portal\PortalLogin;
use App\Models\MemberUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
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

    public function test_delegated_staff_cannot_approve_email_change_request(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $staff = $this->createStaffUser('delegated_staff', 'ws-emk-dhaka', 'staff.regular@example.com');

        $request = MemberChangeRequest::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['email' => $member->email],
            'new_payload' => ['email' => 'approved.email@example.com'],
            'justification' => 'Valid justification for manager approval.',
        ]);

        $service = app(MemberChangeRequestService::class);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Reviewer lacks Workspace Manager authority to approve email changes.');

        $service->approveEmailChangeRequest($request, $staff);
    }

    public function test_workspace_manager_approves_email_change_successfully_and_synchronizes_both_tables(): void
    {
        $fixture = $this->createMemberFixture('old.email@example.com', 'MEM-DHK-2026-0001');
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka', 'manager.dhaka@example.com');

        $newEmail = 'brand.new@example.com';
        $request = MemberChangeRequest::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['email' => 'old.email@example.com'],
            'new_payload' => ['email' => $newEmail],
            'justification' => 'Security manager authorized update for university affiliation.',
        ]);

        $service = app(MemberChangeRequestService::class);
        $service->approveEmailChangeRequest($request, $manager);

        // Assert member table updated
        $this->assertEquals($newEmail, $member->fresh()->email);

        // Assert linked user table updated atomically
        $this->assertEquals($newEmail, $memberUser->fresh()->email);

        // Assert request status updated
        $freshRequest = $request->fresh();
        $this->assertEquals(MemberChangeRequest::STATUS_APPROVED, $freshRequest->status);
        $this->assertEquals($manager->id, $freshRequest->reviewed_by_user_id);
        $this->assertNotNull($freshRequest->reviewed_at);

        // Assert immutable audit log entry per DEC-11 (D-11)
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditLog::EVENT_MEMBER_EMAIL_CHANGED,
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
            'workspace_id' => $member->workspace_id,
            'actor_id' => $manager->id,
            'actor_guard' => 'web',
        ]);
    }

    public function test_toctou_race_condition_blocks_approval_if_email_claimed_prior_to_approval(): void
    {
        $fixture1 = $this->createMemberFixture('original@example.com', 'MEM-DHK-2026-0001');
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka', 'manager.dhaka@example.com');

        $claimedEmail = 'racing.candidate@example.com';

        $request = MemberChangeRequest::create([
            'workspace_id' => $fixture1['member']->workspace_id,
            'member_id' => $fixture1['member']->id,
            'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['email' => 'original@example.com'],
            'new_payload' => ['email' => $claimedEmail],
            'justification' => 'Race condition check justification.',
        ]);

        // Simulating race condition: another member claims the email between submission and approval
        $this->createMemberFixture($claimedEmail, 'MEM-DHK-2026-0002');

        $service = app(MemberChangeRequestService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The requested new email has been claimed by another user prior to approval.');

        $service->approveEmailChangeRequest($request, $manager);
    }

    public function test_member_can_immediately_log_in_with_new_email_address_and_old_email_fails(): void
    {
        $fixture = $this->createMemberFixture('initial@example.com', 'MEM-DHK-2026-0001');
        $member = $fixture['member'];
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka', 'manager@example.com');

        $newEmail = 'updated.login@example.com';
        $request = MemberChangeRequest::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['email' => 'initial@example.com'],
            'new_payload' => ['email' => $newEmail],
            'justification' => 'Immediate authentication test after approval.',
        ]);

        $service = app(MemberChangeRequestService::class);
        $service->approveEmailChangeRequest($request, $manager);

        // Attempt login with old email -> fails
        Livewire::test(PortalLogin::class)
            ->set('identifier', 'initial@example.com')
            ->set('password', 'MemberPass123!')
            ->call('login')
            ->assertHasErrors(['identifier']);

        $this->assertFalse(auth('member')->check());

        // Attempt login with new email -> succeeds
        Livewire::test(PortalLogin::class)
            ->set('identifier', $newEmail)
            ->set('password', 'MemberPass123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $this->assertTrue(auth('member')->check());
        $this->assertEquals($member->user_id, auth('member')->id());
    }

    public function test_livewire_change_request_directory_diff_modal_and_approval_by_manager(): void
    {
        $fixture = $this->createMemberFixture('pre.change@example.com', 'MEM-DHK-2026-0001');
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka', 'manager.review@example.com');

        $request = MemberChangeRequest::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['email' => 'pre.change@example.com'],
            'new_payload' => ['email' => 'post.change@example.com'],
            'justification' => 'Official directory review approval test.',
        ]);

        $test = Livewire::actingAs($manager, 'web')
            ->test(MemberChangeRequestDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSee('Email Address Change (Requires Manager Approval)')
            ->call('openDiffModal', $request->id)
            ->assertSet('isDiffModalOpen', true)
            ->assertSet('selectedRequestId', $request->id)
            ->call('approve')
            ->assertSet('isDiffModalOpen', false)
            ->assertSee('Member email successfully updated across portal credentials and member profile.');

        $this->assertEquals('post.change@example.com', $member->fresh()->email);
        $this->assertEquals('post.change@example.com', $memberUser->fresh()->email);
        $this->assertEquals(MemberChangeRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    public function test_livewire_change_request_directory_restricts_approval_for_delegated_staff(): void
    {
        $fixture = $this->createMemberFixture('staff.blocked@example.com', 'MEM-DHK-2026-0001');
        $member = $fixture['member'];
        $staff = $this->createStaffUser('delegated_staff', 'ws-emk-dhaka', 'staff.unauth@example.com');
        $staff->givePermissionTo(['members.view', 'members.edit']);

        $request = MemberChangeRequest::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['email' => 'staff.blocked@example.com'],
            'new_payload' => ['email' => 'staff.unauthorized.new@example.com'],
            'justification' => 'Delegated staff unauthorized approval test.',
        ]);

        Livewire::actingAs($staff, 'web')
            ->test(MemberChangeRequestDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openDiffModal', $request->id)
            ->assertSet('isDiffModalOpen', true)
            ->assertSee('Approve (Manager Only)')
            ->call('approve')
            ->assertSet('errorMessage', 'Reviewer lacks Workspace Manager authority to approve email changes.');

        // Verify database remains untouched
        $this->assertEquals('staff.blocked@example.com', $member->fresh()->email);
        $this->assertEquals(MemberChangeRequest::STATUS_PENDING, $request->fresh()->status);
    }
}
