<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\MemberChangeRequestService;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Members\MemberChangeRequestDirectory;
use App\Livewire\Portal\Modals\SubmitProfileChangeRequestModal;
use App\Models\MemberUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MemberChangeRequestTest extends TestCase
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
            'name' => 'Workspace Manager',
            'email' => $email,
            'password' => bcrypt('Password123!'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_submitting_change_request_creates_pending_record_without_mutating_member_profile(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];

        $service = app(MemberChangeRequestService::class);

        $request = $service->submitGeneralUpdateRequest(
            $member,
            [
                'contact_number' => '+8801899999999',
                'address_area' => 'Gulshan 2',
                'address_house_no' => '99/B',
            ],
            'I have moved to a new apartment in Gulshan.'
        );

        $this->assertInstanceOf(MemberChangeRequest::class, $request);
        $this->assertEquals(MemberChangeRequest::STATUS_PENDING, $request->status);
        $this->assertEquals(MemberChangeRequest::TYPE_GENERAL_UPDATE, $request->request_type);
        $this->assertEquals('+8801899999999', $request->new_payload['contact_number']);
        $this->assertEquals('+8801712345678', $request->old_payload['contact_number']);

        // Assert member database record remains unmutated
        $freshMember = $member->fresh();
        $this->assertEquals('+8801712345678', $freshMember->contact_number);
        $this->assertEquals('Dhanmondi', $freshMember->address_area);

        // Assert audit log creation
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $member->workspace_id,
            'event_type' => AuditLog::EVENT_MEMBER_CHANGE_REQUEST_SUBMITTED,
            'auditable_type' => MemberChangeRequest::class,
            'auditable_id' => $request->id,
        ]);
    }

    public function test_submitting_change_request_with_no_changes_throws_exception(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];

        $service = app(MemberChangeRequestService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No profile attributes were modified in the submission.');

        $service->submitGeneralUpdateRequest(
            $member,
            [
                'contact_number' => '+8801712345678',
                'address_area' => 'Dhanmondi',
            ],
            'Submitting identical profile values.'
        );
    }

    public function test_duplicate_pending_change_request_is_strictly_blocked(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];

        $service = app(MemberChangeRequestService::class);

        $service->submitGeneralUpdateRequest(
            $member,
            ['contact_number' => '+8801811111111'],
            'First update request.'
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A profile change request is already pending review for this member.');

        $service->submitGeneralUpdateRequest(
            $member,
            ['contact_number' => '+8801822222222'],
            'Second concurrent update request.'
        );
    }

    public function test_non_permitted_fields_cannot_be_manipulated_in_change_request(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];

        $service = app(MemberChangeRequestService::class);

        $request = $service->submitGeneralUpdateRequest(
            $member,
            [
                'first_name' => 'HackerName',
                'member_id' => 'FORGED-ID',
                'status' => 'revoked',
                'contact_number' => '+8801833333333',
            ],
            'Attempting to alter non-permitted identity attributes.'
        );

        $this->assertArrayNotHasKey('first_name', $request->new_payload);
        $this->assertArrayNotHasKey('member_id', $request->new_payload);
        $this->assertArrayNotHasKey('status', $request->new_payload);
        $this->assertArrayHasKey('contact_number', $request->new_payload);
    }

    public function test_submitting_change_request_requires_valid_justification(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];

        $service = app(MemberChangeRequestService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A valid justification of at least 10 characters is required.');

        $service->submitGeneralUpdateRequest(
            $member,
            ['contact_number' => '+8801844444444'],
            'Short'
        );
    }

    public function test_portal_modal_renders_and_submits_change_request_via_livewire(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];

        Livewire::actingAs($memberUser, 'member')
            ->test(SubmitProfileChangeRequestModal::class, ['member' => $member])
            ->call('openModal')
            ->assertSet('isOpen', true)
            ->assertSet('contact_number', '+8801712345678')
            ->set('contact_number', '+8801877777777')
            ->set('justification', 'Updated contact number for business communications.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('isOpen', false)
            ->assertDispatched('change-request-submitted');

        $this->assertDatabaseHas('member_change_requests', [
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_GENERAL_UPDATE,
            'status' => MemberChangeRequest::STATUS_PENDING,
        ]);
    }

    public function test_portal_modal_disables_submission_when_pending_request_exists(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];

        // Create an existing pending request
        MemberChangeRequest::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_GENERAL_UPDATE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['contact_number' => $member->contact_number],
            'new_payload' => ['contact_number' => '+8801888888888'],
            'justification' => 'Already pending review from previous submission.',
        ]);

        Livewire::actingAs($memberUser, 'member')
            ->test(SubmitProfileChangeRequestModal::class, ['member' => $member])
            ->call('openModal')
            ->assertSet('hasPendingRequest', true)
            ->assertSee('Change Request Already Pending')
            ->set('contact_number', '+8801899999999')
            ->set('justification', 'Another request should fail.')
            ->call('submit')
            ->assertSet('hasPendingRequest', true);
    }

    public function test_staff_can_view_workspace_pending_change_requests_directory(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];

        $changeRequest = MemberChangeRequest::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'request_type' => MemberChangeRequest::TYPE_GENERAL_UPDATE,
            'status' => MemberChangeRequest::STATUS_PENDING,
            'old_payload' => ['contact_number' => $member->contact_number],
            'new_payload' => ['contact_number' => '+8801812345678'],
            'justification' => 'Relocating to another district for work.',
        ]);

        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka');

        $response = $this->actingAs($manager, 'web')
            ->get(route('crm.members.change_requests', ['workspace' => 'ws-emk-dhaka']));

        $response->assertStatus(200);
        $response->assertSee('Member Profile Change Requests');
        $response->assertSee($member->member_id);
        $response->assertSee('Relocating to another district');
    }

    public function test_cross_workspace_staff_cannot_access_or_review_requests(): void
    {
        $fixture = $this->createMemberFixture();

        // Create Chattogram workspace
        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        $crossStaff = $this->createStaffUser('workspace_manager', 'ws-chattogram', 'ctg.manager@emk.test');

        // Cross-workspace GET route access blocked with 403 Forbidden
        $response = $this->actingAs($crossStaff, 'web')
            ->get(route('crm.members.change_requests', ['workspace' => 'ws-emk-dhaka']));

        $response->assertStatus(403);
    }

    public function test_approving_change_request_updates_member_record_and_emits_audit_log(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka');

        $service = app(MemberChangeRequestService::class);

        $changeRequest = $service->submitGeneralUpdateRequest(
            $member,
            [
                'contact_number' => '+8801855555555',
                'address_house_no' => 'Flat 4B, Building 10',
                'occupation' => 'entrepreneur',
            ],
            'Valid business update justification.'
        );

        $updatedMember = $service->approveRequest($changeRequest, $manager);

        // Assert member model updated
        $this->assertEquals('+8801855555555', $updatedMember->contact_number);
        $this->assertEquals('Flat 4B, Building 10', $updatedMember->address_house_no);
        $this->assertEquals('entrepreneur', $updatedMember->occupation);

        // Assert change request transitioned to approved
        $freshRequest = $changeRequest->fresh();
        $this->assertEquals(MemberChangeRequest::STATUS_APPROVED, $freshRequest->status);
        $this->assertEquals($manager->id, $freshRequest->reviewed_by_user_id);
        $this->assertNotNull($freshRequest->reviewed_at);

        // Assert audit log creation
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $member->workspace_id,
            'actor_id' => $manager->id,
            'event_type' => AuditLog::EVENT_MEMBER_CHANGE_REQUEST_APPROVED,
            'auditable_type' => MemberChangeRequest::class,
            'auditable_id' => $changeRequest->id,
        ]);
    }

    public function test_rejecting_change_request_requires_notes_and_leaves_member_intact(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka');

        $service = app(MemberChangeRequestService::class);

        $changeRequest = $service->submitGeneralUpdateRequest(
            $member,
            ['contact_number' => '+8801866666666'],
            'Request to change phone number.'
        );

        // Rejection without sufficient notes throws exception
        try {
            $service->rejectRequest($changeRequest, $manager, 'No');
            $this->fail('Expected DomainException for short manager notes');
        } catch (DomainException $e) {
            $this->assertStringContainsString('at least 5 characters', $e->getMessage());
        }

        // Proper rejection
        $rejectedRequest = $service->rejectRequest($changeRequest, $manager, 'Phone number format verification failed.');

        $this->assertEquals(MemberChangeRequest::STATUS_REJECTED, $rejectedRequest->status);
        $this->assertEquals('Phone number format verification failed.', $rejectedRequest->manager_notes);
        $this->assertEquals($manager->id, $rejectedRequest->reviewed_by_user_id);

        // Member record remains untouched
        $freshMember = $member->fresh();
        $this->assertEquals('+8801712345678', $freshMember->contact_number);

        // Assert audit log
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $member->workspace_id,
            'actor_id' => $manager->id,
            'event_type' => AuditLog::EVENT_MEMBER_CHANGE_REQUEST_REJECTED,
            'auditable_type' => MemberChangeRequest::class,
            'auditable_id' => $changeRequest->id,
        ]);
    }

    public function test_already_decided_request_cannot_be_approved_or_rejected_again(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka');

        $service = app(MemberChangeRequestService::class);

        $changeRequest = $service->submitGeneralUpdateRequest(
            $member,
            ['contact_number' => '+8801877777777'],
            'Valid justification for phone update.'
        );

        $service->approveRequest($changeRequest, $manager);

        // Re-approving fails
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot approve a change request that is in status [approved]');

        $service->approveRequest($changeRequest->fresh(), $manager);
    }

    public function test_staff_without_edit_permission_cannot_approve_request(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];

        // Staff with only members.view
        $readOnlyStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Read Only Staff',
            'email' => 'readonly.staff@emk.test',
            'password' => bcrypt('Password123!'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $readOnlyStaff->givePermissionTo('members.view');

        $service = app(MemberChangeRequestService::class);

        $changeRequest = $service->submitGeneralUpdateRequest(
            $member,
            ['contact_number' => '+8801888888888'],
            'Valid justification for phone update.'
        );

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Reviewer lacks members.edit or workspace_manager authority.');

        $service->approveRequest($changeRequest, $readOnlyStaff);
    }

    public function test_change_request_directory_diff_modal_and_approval_via_livewire(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka');

        $service = app(MemberChangeRequestService::class);
        $changeRequest = $service->submitGeneralUpdateRequest(
            $member,
            [
                'contact_number' => '+8801899999999',
                'address_area' => 'Banani DOHS',
            ],
            'Relocated office to Banani DOHS.'
        );

        Livewire::actingAs($manager, 'web')
            ->test(MemberChangeRequestDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openDiffModal', $changeRequest->id)
            ->assertSet('isDiffModalOpen', true)
            ->assertSet('selectedRequestId', $changeRequest->id)
            ->assertSee('Banani DOHS')
            ->assertSee('+8801899999999')
            ->call('approve')
            ->assertSet('isDiffModalOpen', false);

        $this->assertEquals('approved', $changeRequest->fresh()->status);
        $this->assertEquals('Banani DOHS', $member->fresh()->address_area);
        $this->assertEquals('+8801899999999', $member->fresh()->contact_number);
    }

    public function test_change_request_directory_rejection_via_livewire(): void
    {
        $fixture = $this->createMemberFixture();
        $member = $fixture['member'];
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka');

        $service = app(MemberChangeRequestService::class);
        $changeRequest = $service->submitGeneralUpdateRequest(
            $member,
            ['contact_number' => '+8801810101010'],
            'Changed primary phone number.'
        );

        Livewire::actingAs($manager, 'web')
            ->test(MemberChangeRequestDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openDiffModal', $changeRequest->id)
            ->assertSet('isDiffModalOpen', true)
            ->set('rejectionNotes', '')
            ->call('reject')
            ->assertHasErrors(['rejectionNotes'])
            ->set('rejectionNotes', 'Provided phone number could not be verified by front desk.')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertSet('isDiffModalOpen', false);

        $this->assertEquals('rejected', $changeRequest->fresh()->status);
        $this->assertEquals('Provided phone number could not be verified by front desk.', $changeRequest->fresh()->manager_notes);
        $this->assertEquals('+8801712345678', $member->fresh()->contact_number);
    }
}
