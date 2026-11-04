<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Members\Mail\ManagerEmailChangeAlertMail;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\MemberChangeRequestService;
use App\Domain\Members\Services\MemberRenewalService;
use App\Livewire\Members\MemberChangeRequestDirectory;
use App\Livewire\Portal\MemberPortalProfile;
use App\Livewire\Portal\Modals\SubmitProfileChangeRequestModal;
use App\Livewire\Portal\PortalFirstPasswordReset;
use App\Livewire\Portal\PortalLogin;
use App\Models\MemberUser;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class EndToEndMemberPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper to provision a complete Member fixture with linked Lead, Term, and MemberUser.
     */
    protected function createMemberFixture(
        string $email = 'e2e.member@emk.test',
        string $memberId = 'EMK-2026-00001',
        string $password = 'TempPass123!',
        bool $mustResetPassword = false,
        string $status = 'active',
        ?Carbon $expiryDate = null,
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
            'first_name' => 'Kazi',
            'middle_name' => 'Nazrul',
            'last_name' => 'Islam',
            'email' => $email,
            'contact_number' => '+8801700112233',
            'date_of_birth' => '1996-05-25',
            'age_at_submission' => 30,
            'sex' => 'male',
            'address_house_no' => 'House 7',
            'address_road_no' => 'Road 11',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'writer',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'master',
            'area_of_subject' => 'Literature',
            'submitted_at' => now(),
        ]);

        $memberUser = MemberUser::create([
            'workspace_id' => $workspaceId,
            'name' => 'Kazi Nazrul Islam',
            'email' => $email,
            'password' => Hash::make($password),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => $mustResetPassword,
        ]);
        $memberUser->assignRole('member');

        $effectiveExpiry = $expiryDate ?? Carbon::today()->addMonths(12);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $memberId,
            'workspace_id' => $workspaceId,
            'lead_id' => $lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $term->id,
            'status' => $status,
            'first_name' => 'Kazi',
            'middle_name' => 'Nazrul',
            'last_name' => 'Islam',
            'email' => $email,
            'contact_number' => '+8801700112233',
            'date_of_birth' => '1996-05-25',
            'sex' => 'male',
            'address_house_no' => 'House 7',
            'address_road_no' => 'Road 11',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'writer',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'master',
            'area_of_subject' => 'Literature',
            'start_date' => Carbon::today()->subMonths(2)->toDateString(),
            'expiry_date' => $effectiveExpiry->toDateString(),
        ]);

        return compact('member', 'memberUser', 'lead', 'term');
    }

    /**
     * Helper to provision a staff user with specific role and permissions.
     */
    protected function createStaffUser(
        string $role = 'workspace_manager',
        string $workspaceId = 'ws-emk-dhaka',
        string $email = 'manager.portal@emk.test'
    ): User {
        $user = User::create([
            'workspace_id' => $workspaceId,
            'name' => 'Workspace Manager',
            'email' => $email,
            'password' => bcrypt('ManagerPass123!'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Scenario 1: PORT-01 Authentication, dual-identifier login, first-login forced password reset.
     */
    public function test_scenario_1_authentication_and_forced_password_reset(): void
    {
        $fixture = $this->createMemberFixture(
            email: 'portal.s1@emk.test',
            memberId: 'EMK-2026-00001',
            password: 'TempPassword123!',
            mustResetPassword: true
        );
        $memberUser = $fixture['memberUser'];

        // 1. Dual-identifier login via Member ID
        Livewire::test(PortalLogin::class)
            ->set('identifier', 'EMK-2026-00001')
            ->set('password', 'TempPassword123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $this->assertTrue(Auth::guard('member')->check());
        $this->assertEquals($memberUser->id, Auth::guard('member')->id());

        // 2. Interception: requesting /portal/profile redirects to /portal/password/reset
        $response = $this->actingAs($memberUser, 'member')->get(route('portal.profile'));
        $response->assertRedirect(route('portal.password.reset'));

        // 3. Submit valid new password via first-login reset component
        Livewire::actingAs($memberUser, 'member')
            ->test(PortalFirstPasswordReset::class)
            ->set('current_password', 'TempPassword123!')
            ->set('password', 'PermanentSecret999!')
            ->set('password_confirmation', 'PermanentSecret999!')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $memberUser->refresh();
        $this->assertFalse($memberUser->must_reset_password);
        $this->assertTrue(Hash::check('PermanentSecret999!', $memberUser->password));

        // 4. Direct profile access succeeds after reset
        $responseAfterReset = $this->actingAs($memberUser, 'member')->get(route('portal.profile'));
        $responseAfterReset->assertOk();

        // 5. Logout and re-login using Email with the new password
        $this->post(route('portal.logout'));
        $this->assertFalse(Auth::guard('member')->check());

        Livewire::test(PortalLogin::class)
            ->set('identifier', 'portal.s1@emk.test')
            ->set('password', 'PermanentSecret999!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $this->assertTrue(Auth::guard('member')->check());
    }

    /**
     * Scenario 2: PORT-02 Read-only profile view, strict exclusion of staff notes & audit logs, hidden invoices, private document streaming with IDOR defense.
     */
    public function test_scenario_2_read_only_presentation_and_information_isolation(): void
    {
        Storage::fake('private');

        $fixture = $this->createMemberFixture(
            email: 'portal.s2@emk.test',
            memberId: 'EMK-2026-00002'
        );
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];
        $lead = $fixture['lead'];

        // 1. Plant internal staff note
        DB::table('lead_verifications')->insert([
            'lead_id' => $lead->id,
            'stream' => 'document',
            'status' => 'verified',
            'verified_by_user_id' => 1,
            'verified_at' => now(),
            'evidence_payload' => json_encode(['ok' => true]),
            'notes' => 'INTERNAL STAFF SENSITIVE NOTE #999',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Plant internal confidential audit log
        AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => 1,
            'actor_guard' => 'web',
            'event_type' => 'internal_staff_audit',
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
            'reason' => 'CONFIDENTIAL RISK INVESTIGATION #888',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'InternalSystem',
            'created_at' => now(),
        ]);

        // 3. Plant hidden conversion invoice
        Invoice::create([
            'invoice_number' => 'INV-CONV-SECRET-777',
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'lead_id' => $lead->id,
            'amount_bdt' => 1200.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'SECRET-PAY-REF-666',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // 4. Assert profile view renders personal attributes and strictly hides internal data
        $component = Livewire::actingAs($memberUser, 'member')
            ->test(MemberPortalProfile::class);

        $component->assertSee('Kazi Nazrul Islam');
        $component->assertSee('EMK-2026-00002');
        $component->assertSee('+8801700112233');
        $component->assertSee('Banani');
        $component->assertSee('1 Year Regular Membership');

        // Information disclosure defense assertions
        $component->assertDontSee('INTERNAL STAFF SENSITIVE NOTE #999');
        $component->assertDontSee('CONFIDENTIAL RISK INVESTIGATION #888');
        $component->assertDontSee('INV-CONV-SECRET-777');
        $component->assertDontSee('SECRET-PAY-REF-666');

        // 5. Member downloads own private document
        $docPath = 'workspaces/ws-emk-dhaka/leads/' . $lead->uuid . '/nid.pdf';
        Storage::disk('private')->put($docPath, 'BINARY_NID_CONTENT');

        $attachment = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'document_type' => 'identity_document',
            'storage_path' => $docPath,
            'original_filename' => 'national_identity.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 18,
            'file_hash' => hash('sha256', 'BINARY_NID_CONTENT'),
            'youth_protection' => false,
        ]);

        $response = $this->actingAs($memberUser, 'member')
            ->get(route('portal.documents.download', ['attachment' => $attachment->id]));
        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'inline; filename="national_identity.pdf"');

        // 6. IDOR defense: Attempt to download another member's document returns 403 Forbidden
        $otherFixture = $this->createMemberFixture(
            email: 'other.member@emk.test',
            memberId: 'EMK-2026-00003'
        );
        $otherDocPath = 'workspaces/ws-emk-dhaka/leads/' . $otherFixture['lead']->uuid . '/other_nid.pdf';
        Storage::disk('private')->put($otherDocPath, 'OTHER_BINARY_CONTENT');

        $otherAttachment = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $otherFixture['lead']->id,
            'document_type' => 'identity_document',
            'storage_path' => $otherDocPath,
            'original_filename' => 'other_national_id.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'file_hash' => hash('sha256', 'OTHER_BINARY_CONTENT'),
            'youth_protection' => false,
        ]);

        $idorResponse = $this->actingAs($memberUser, 'member')
            ->get(route('portal.documents.download', ['attachment' => $otherAttachment->id]));
        $idorResponse->assertStatus(403);
    }

    /**
     * Scenario 3: PORT-03 Profile change request, pending duplicate prevention, staff diff review and approval.
     */
    public function test_scenario_3_profile_change_request_and_staff_approval(): void
    {
        $fixture = $this->createMemberFixture(
            email: 'portal.s3@emk.test',
            memberId: 'EMK-2026-00004'
        );
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];

        // 1. Submit general demographic update
        Livewire::actingAs($memberUser, 'member')
            ->test(SubmitProfileChangeRequestModal::class, ['member' => $member])
            ->call('openModal')
            ->set('contact_number', '+8801999887766')
            ->set('address_house_no', 'Flat 4B')
            ->set('address_area', 'Mohakhali DOHS')
            ->set('justification', 'Relocated to new permanent residence and updated phone.')
            ->call('submit')
            ->assertSet('isOpen', false)
            ->assertSet('hasPendingRequest', true);

        $this->assertDatabaseHas('member_change_requests', [
            'member_id' => $member->id,
            'request_type' => 'general_update',
            'status' => 'pending',
        ]);

        // 2. Prevent duplicate concurrent general update
        $service = app(MemberChangeRequestService::class);
        try {
            $service->submitGeneralUpdateRequest($member, ['contact_number' => '+8801111222233'], 'Second concurrent attempt justification.');
            $this->fail('Expected DomainException for duplicate pending general request.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already pending review', $e->getMessage());
        }

        // 3. Manager reviews and approves diff in MemberChangeRequestDirectory
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka', 'mgr.s3@emk.test');
        $changeRequest = MemberChangeRequest::where('member_id', $member->id)->first();

        Livewire::actingAs($manager, 'web')
            ->test(MemberChangeRequestDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openDiffModal', $changeRequest->id)
            ->assertSet('isDiffModalOpen', true)
            ->assertSee('Mohakhali DOHS')
            ->call('approve')
            ->assertSet('isDiffModalOpen', false);

        // 4. Verify member entity updated and audit log recorded
        $member->refresh();
        $this->assertEquals('+8801999887766', $member->contact_number);
        $this->assertEquals('Mohakhali DOHS', $member->address_area);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $manager->id,
            'event_type' => AuditLog::EVENT_MEMBER_CHANGE_REQUEST_APPROVED,
            'auditable_type' => MemberChangeRequest::class,
            'auditable_id' => $changeRequest->id,
        ]);
    }

    /**
     * Scenario 4: PORT-04 Dedicated email change, manager alert email, manager approval, atomic dual-entity update.
     */
    public function test_scenario_4_dedicated_email_change_workflow(): void
    {
        Mail::fake();

        $fixture = $this->createMemberFixture(
            email: 'original.email@emk.test',
            memberId: 'EMK-2026-00005',
            password: 'MemberPass123!'
        );
        $member = $fixture['member'];
        $memberUser = $fixture['memberUser'];

        // 1. Submit email change request
        $service = app(MemberChangeRequestService::class);
        $newEmail = 'brand.new.email@emk.test';
        $justification = 'Updating primary email address to institutional domain.';

        $request = $service->submitEmailChangeRequest($member, $newEmail, $justification);
        $this->assertEquals('pending', $request->status);

        // 2. Verify manager alert email queued
        Mail::assertQueued(ManagerEmailChangeAlertMail::class, function ($mail) use ($newEmail) {
            return $mail->newEmail === $newEmail;
        });

        // 3. Manager approves email change
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka', 'mgr.s4@emk.test');
        $service->approveEmailChangeRequest($request, $manager);

        // 4. Verify atomic synchronization across both members and users tables
        $member->refresh();
        $memberUser->refresh();
        $this->assertEquals($newEmail, $member->email);
        $this->assertEquals($newEmail, $memberUser->email);

        // 5. Member logs into portal using new email address
        Livewire::test(PortalLogin::class)
            ->set('identifier', $newEmail)
            ->set('password', 'MemberPass123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $this->assertTrue(Auth::guard('member')->check());

        // Old email fails authentication
        $this->post(route('portal.logout'));
        Livewire::test(PortalLogin::class)
            ->set('identifier', 'original.email@emk.test')
            ->set('password', 'MemberPass123!')
            ->call('login')
            ->assertHasErrors(['identifier']);
    }

    /**
     * Scenario 5: PORT-05 30-day window check, bKash TxID uniqueness, early vs late renewal expiry math, paid invoice creation.
     */
    public function test_scenario_5_renewal_window_and_dynamic_expiry_calculations(): void
    {
        // 1. Test active member with 60 days remaining: renewal submission blocked
        Carbon::setTestNow(Carbon::parse('2026-11-01'));
        $fixture = $this->createMemberFixture(
            email: 'renewal.s5@emk.test',
            memberId: 'EMK-2026-00006',
            status: 'active',
            expiryDate: Carbon::parse('2026-12-31') // 60 days away
        );
        $member = $fixture['member'];
        $term = $fixture['term'];
        $renewalService = app(MemberRenewalService::class);

        $this->assertFalse($renewalService->isRenewalWindowOpen($member));
        try {
            $renewalService->submitRenewalRequest($member, $term->id, 'EARLYTX123');
            $this->fail('Expected DomainException for premature renewal attempt.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Renewal window is not open', $e->getMessage());
        }

        // 2. Fast-forward to 20 days prior to expiry (Dec 11, 2026): renewal window opens
        Carbon::setTestNow(Carbon::parse('2026-12-11'));
        $this->assertTrue($renewalService->isRenewalWindowOpen($member));

        // Submit early renewal
        $earlyRequest = $renewalService->submitRenewalRequest($member, $term->id, 'EARLYTX123');

        // 3. Manager approves early renewal: new expiry appends 12 months to previous expiry (2026-12-31 -> 2027-12-31)
        $manager = $this->createStaffUser('workspace_manager', 'ws-emk-dhaka', 'mgr.s5@emk.test');
        $updatedMember = $renewalService->approveRenewal($earlyRequest, $manager);

        $this->assertEquals('2027-12-31', $updatedMember->expiry_date->toDateString());
        $this->assertEquals('active', $updatedMember->status);

        // Verify paid invoice created
        $this->assertDatabaseHas('invoices', [
            'member_id' => $member->id,
            'payment_reference' => 'EARLYTX123',
            'status' => 'paid',
        ]);

        // 4. Test Late Renewal: Expire member, submit late renewal, verify validity restarts on approval date
        Carbon::setTestNow(Carbon::parse('2028-02-15')); // member has lapsed
        $member->status = 'inactive';
        $member->expiry_date = '2027-12-31';
        $member->save();

        $this->assertTrue($renewalService->isRenewalWindowOpen($member));

        $lateRequest = $renewalService->submitRenewalRequest($member, $term->id, 'LATETX1234');
        $renewedLateMember = $renewalService->approveRenewal($lateRequest, $manager);

        // Late renewal math: Start date is approval date (2028-02-15), Expiry is 12 months later (2029-02-15)
        $this->assertEquals('2028-02-15', $renewedLateMember->start_date->toDateString());
        $this->assertEquals('2029-02-15', $renewedLateMember->expiry_date->toDateString());
        $this->assertEquals('active', $renewedLateMember->status);

        $this->assertDatabaseHas('invoices', [
            'member_id' => $member->id,
            'payment_reference' => 'LATETX1234',
            'status' => 'paid',
        ]);
    }
}
