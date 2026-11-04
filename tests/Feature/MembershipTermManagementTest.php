<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Members\ManageTerms;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipTermManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staff;
    protected User $otherManager;
    protected MembershipTerm $standardTerm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Ensure other workspace exists
        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        // 1. Workspace Manager (has platform_services.manage_settings)
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Terms Manager',
            'email' => 'manager.terms.test@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Regular Staff (delegated_staff role, lacks platform_services.manage_settings)
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Terms Staff',
            'email' => 'staff.terms.test@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // 3. Cross-workspace Manager (belongs to ws-chattogram)
        $this->otherManager = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Manager',
            'email' => 'manager.ctg.test@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->otherManager->assignRole('workspace_manager');

        // Standard term in ws-emk-dhaka
        $this->standardTerm = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_standard_1yr'],
            [
                'name' => 'Standard 1-Year Membership',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]
        );
    }

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        $response = $this->get('/crm/ws-emk-dhaka/settings/terms');
        $response->assertRedirect('/login');
    }

    public function test_non_managerial_staff_receive_403_forbidden(): void
    {
        $response = $this->actingAs($this->staff, 'web')
            ->get('/crm/ws-emk-dhaka/settings/terms');

        $response->assertStatus(403);
    }

    public function test_workspace_manager_can_view_terms_index_route(): void
    {
        $response = $this->actingAs($this->manager, 'web')
            ->get('/crm/ws-emk-dhaka/settings/terms');

        $response->assertStatus(200);
        $response->assertSee('Membership Terms');
        $response->assertSee('Standard 1-Year Membership');
        $response->assertSee('term_standard_1yr');
    }

    public function test_cross_workspace_manager_receives_403(): void
    {
        $response = $this->actingAs($this->otherManager, 'web')
            ->get('/crm/ws-emk-dhaka/settings/terms');

        $response->assertStatus(403);
    }

    public function test_workspace_manager_can_create_new_term(): void
    {
        Livewire::actingAs($this->manager, 'web')
            ->test(ManageTerms::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openCreateModal')
            ->set('name', 'Executive 2-Year Membership')
            ->set('code', 'term_exec_2yr')
            ->set('durationMonths', '24')
            ->set('feeBdt', '2500.00')
            ->set('isActive', true)
            ->call('createTerm')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('membership_terms', [
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Executive 2-Year Membership',
            'code' => 'term_exec_2yr',
            'duration_months' => 24,
            'fee_bdt' => 2500.00,
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_MEMBERSHIP_TERM_CREATED,
            'auditable_type' => MembershipTerm::class,
        ]);
    }

    public function test_term_code_uniqueness_within_workspace(): void
    {
        // Duplicate code in same workspace should fail validation
        Livewire::actingAs($this->manager, 'web')
            ->test(ManageTerms::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openCreateModal')
            ->set('name', 'Another Standard Term')
            ->set('code', 'term_standard_1yr')
            ->set('durationMonths', '12')
            ->set('feeBdt', '1000.00')
            ->call('createTerm')
            ->assertHasErrors(['code']);

        // Same code in another workspace should succeed
        Livewire::actingAs($this->otherManager, 'web')
            ->test(ManageTerms::class, ['workspace' => 'ws-chattogram'])
            ->call('openCreateModal')
            ->set('name', 'Chattogram 1-Year')
            ->set('code', 'term_standard_1yr')
            ->set('durationMonths', '12')
            ->set('feeBdt', '1000.00')
            ->call('createTerm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('membership_terms', [
            'workspace_id' => 'ws-chattogram',
            'code' => 'term_standard_1yr',
        ]);
    }

    public function test_term_code_format_validation(): void
    {
        Livewire::actingAs($this->manager, 'web')
            ->test(ManageTerms::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openCreateModal')
            ->set('name', 'Invalid Code Term')
            ->set('code', 'INVALID CODE!')
            ->set('durationMonths', '12')
            ->set('feeBdt', '1000.00')
            ->call('createTerm')
            ->assertHasErrors(['code']);
    }

    public function test_manager_can_edit_term_without_members(): void
    {
        $term = MembershipTerm::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Unassigned 6-Month Term',
            'code' => 'term_unassigned_6m',
            'duration_months' => 6,
            'fee_bdt' => 600.00,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->manager, 'web')
            ->test(ManageTerms::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('editTerm', $term->id)
            ->assertSet('isLocked', false)
            ->set('name', 'Updated 6-Month Term')
            ->set('durationMonths', '6')
            ->set('feeBdt', '750.00')
            ->call('updateTerm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('membership_terms', [
            'id' => $term->id,
            'name' => 'Updated 6-Month Term',
            'fee_bdt' => 750.00,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_MEMBERSHIP_TERM_UPDATED,
            'auditable_type' => MembershipTerm::class,
            'auditable_id' => $term->id,
        ]);
    }

    public function test_term_with_active_members_locks_code_and_duration_from_mutation(): void
    {
        // Assign a member to standardTerm
        $lead = Lead::create([
            'uuid' => Str::uuid()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXIDLOCK01',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'email' => 'test.member.lock@example.com',
            'contact_number' => '+8801700000000',
            'date_of_birth' => '1995-01-01',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => '10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1205',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'BUET',
            'highest_certification' => 'undergraduate',
            'area_of_subject' => 'Engineering',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now()->subDays(10),
        ]);

        $memberUser = User::create([
            'name' => 'Test Member',
            'email' => 'test.member.lock@example.com',
            'password' => bcrypt('secret123'),
            'workspace_id' => 'ws-emk-dhaka',
            'guard' => 'member',
            'is_active' => true,
        ]);

        $member = Member::create([
            'uuid' => Str::uuid()->toString(),
            'member_id' => 'EMK-2026-00055',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $this->standardTerm->id,
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'email' => 'test.member.lock@example.com',
            'contact_number' => '+8801700000000',
            'date_of_birth' => '1995-01-01',
            'sex' => 'male',
            'address_house_no' => '10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1205',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'BUET',
            'highest_certification' => 'undergraduate',
            'area_of_subject' => 'Engineering',
            'start_date' => now(),
            'expiry_date' => now()->addMonths(12),
        ]);

        // When opening edit modal, isLocked must be true
        $test = Livewire::actingAs($this->manager, 'web')
            ->test(ManageTerms::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('editTerm', $this->standardTerm->id)
            ->assertSet('isLocked', true);

        // Attempting to mutate duration_months must fail
        $test->set('durationMonths', '36')
            ->call('updateTerm')
            ->assertHasErrors(['durationMonths']);

        // Verify DB value was not tampered
        $this->assertEquals(12, $this->standardTerm->fresh()->duration_months);

        // Modifying allowed fields (name, feeBdt) must succeed
        $test->set('durationMonths', (string) $this->standardTerm->duration_months)
            ->set('name', 'Standard 1-Year Membership (Revised Fee)')
            ->set('feeBdt', '1200.00')
            ->call('updateTerm')
            ->assertHasNoErrors();

        $this->assertEquals('Standard 1-Year Membership (Revised Fee)', $this->standardTerm->fresh()->name);
        $this->assertEquals('1200.00', (string) $this->standardTerm->fresh()->fee_bdt);
    }

    public function test_toggle_term_active_status(): void
    {
        $initialStatus = $this->standardTerm->is_active;

        Livewire::actingAs($this->manager, 'web')
            ->test(ManageTerms::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('toggleActive', $this->standardTerm->id);

        $this->assertEquals(!$initialStatus, (bool) $this->standardTerm->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_MEMBERSHIP_TERM_UPDATED,
            'auditable_type' => MembershipTerm::class,
            'auditable_id' => $this->standardTerm->id,
        ]);
    }
}
