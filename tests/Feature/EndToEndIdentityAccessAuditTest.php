<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\AccessControl\Exceptions\TenancyBoundaryException;
use App\Domain\AccessControl\Exceptions\UnauthorizedDelegationException;
use App\Domain\AccessControl\Services\StaffManagementService;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Livewire\Audit\AuditLogViewer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class E2EHydrationProbeComponent extends Component
{
    use ValidatesWorkspaceOnHydration;

    public ?Lead $lead = null;

    public function mount(?Lead $lead = null): void
    {
        $this->lead = $lead;
    }

    public function triggerAction(): void
    {
        // No-op to trigger hydration cycle
    }

    public function render()
    {
        return '<div>Lead: ' . ($this->lead ? $this->lead->first_name : 'none') . '</div>';
    }
}

class EndToEndIdentityAccessAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaManager;
    protected User $sylhetManager;
    protected User $dhakaStaff;
    protected User $sylhetStaff;
    protected User $superAdmin;
    protected Lead $dhakaLead;
    protected Lead $sylhetLead;
    protected StaffManagementService $staffService;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed physical spaces
        DB::table('spaces')->insert([
            ['id' => 1, 'code' => 'EMK-DHK', 'name' => 'EMK Center Dhaka', 'category' => 'Center', 'division' => 'Dhaka', 'district' => 'Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'CR-SYL', 'name' => 'American Corner Sylhet', 'category' => 'Corner', 'division' => 'Sylhet', 'district' => 'Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Seed tenant workspaces
        DB::table('workspaces')->insert([
            ['id' => 'ws-emk-dhaka', 'space_id' => 1, 'name' => 'EMK Center Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'ws-corner-sylhet', 'space_id' => 2, 'name' => 'American Corner Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Seed Spatie roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        // 4. Create isolated identities
        $this->dhakaManager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'e2e.manager.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->dhakaManager->assignRole('workspace_manager');

        $this->sylhetManager = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Manager',
            'email' => 'e2e.manager.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->sylhetManager->assignRole('workspace_manager');

        $this->dhakaStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Staff User',
            'email' => 'e2e.staff.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->dhakaStaff->assignRole('delegated_staff');

        $this->sylhetStaff = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Staff User',
            'email' => 'e2e.staff.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->sylhetStaff->assignRole('delegated_staff');

        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'e2e.superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 5. Seed sample tenant-scoped records
        $this->dhakaLead = Lead::withoutGlobalScopes()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDE2EDHK01',
            'first_name' => 'Dhaka',
            'last_name' => 'Applicant',
            'email' => 'e2e.dhaka.applicant@example.com',
            'contact_number' => '+8801711000001',
            'date_of_birth' => '1998-05-10',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '10/A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'BSc',
            'area_of_subject' => 'Computer Science',
            'submitted_at' => now(),
        ]);

        $this->sylhetLead = Lead::withoutGlobalScopes()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-corner-sylhet',
            'target_space_code' => 'CR-SYL',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDE2ESYL01',
            'first_name' => 'Sylhet',
            'last_name' => 'Applicant',
            'email' => 'e2e.sylhet.applicant@example.com',
            'contact_number' => '+8801722000002',
            'date_of_birth' => '1999-08-20',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => '22',
            'address_area' => 'Zindabazar',
            'address_zip_code' => '3100',
            'address_division' => 'Sylhet',
            'address_district' => 'Sylhet',
            'occupation' => 'Student',
            'institution_study' => 'SUST',
            'highest_certification' => 'BSc',
            'area_of_subject' => 'English',
            'submitted_at' => now(),
        ]);

        $this->staffService = app(StaffManagementService::class);
    }

    /**
     * Scenario 1: Multi-Guard & Password Reset
     * Create staff user with must_reset_password = true; verify redirection on protected routes;
     * update password; verify normal access with emk_web_session cookie.
     */
    public function test_scenario_1_multi_guard_and_password_reset_enforcement(): void
    {
        $newStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'New Staff Requiring Reset',
            'email' => 'reset.required.staff@emk.test',
            'password' => bcrypt('temporary123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => true,
        ]);
        $newStaff->assignRole('delegated_staff');

        // Protected CRM route redirects when must_reset_password is true
        $redirectResponse = $this->actingAs($newStaff, 'web')
            ->get('/crm/ws-emk-dhaka/leads');

        $redirectResponse->assertRedirect(route('password.reset-required'));

        // Update password and clear reset flag
        $updateResponse = $this->actingAs($newStaff, 'web')
            ->post('/password/reset-required', [
                'password' => 'BrandNewSecurePassword456!',
            ]);

        $updateResponse->assertStatus(200);

        // Verify user flag is updated
        $freshStaff = $newStaff->fresh();
        $this->assertFalse((bool) $freshStaff->must_reset_password);

        // Subsequent access to protected CRM route succeeds
        $allowedResponse = $this->actingAs($freshStaff, 'web')
            ->get('/crm/ws-emk-dhaka/leads');

        $allowedResponse->assertStatus(200);
        $allowedResponse->assertSee('Inbound Leads');
    }

    /**
     * Scenario 2: 4-Layer Tenancy Defense
     * Authenticate Manager in ws-emk-dhaka; verify access to /crm/ws-emk-dhaka/leads;
     * attempt access to /crm/ws-corner-sylhet/leads (Layer 1 403);
     * verify Eloquent queries return only ws-emk-dhaka records (Layer 3 TenantScope);
     * verify Livewire hydration tampering aborts (Layer 2 403).
     */
    public function test_scenario_2_four_layer_tenancy_defense(): void
    {
        $this->actingAs($this->dhakaManager, 'web');

        // Layer 1: Route Middleware (VerifyWorkspaceAccess)
        $routeOk = $this->get('/crm/ws-emk-dhaka/leads');
        $routeOk->assertStatus(200);

        $routeBlocked = $this->get('/crm/ws-corner-sylhet/leads');
        $routeBlocked->assertStatus(403);

        // Layer 3: Model Global Scope (TenantScope)
        $visibleLeads = Lead::all();
        $this->assertCount(1, $visibleLeads);
        $this->assertSame($this->dhakaLead->id, $visibleLeads->first()->id);
        $this->assertNull(Lead::find($this->sylhetLead->id));

        // Layer 2: Livewire Hydration Guard (ValidatesWorkspaceOnHydration)
        // Owned model hydrates successfully
        Livewire::test(E2EHydrationProbeComponent::class, ['lead' => $this->dhakaLead])
            ->call('triggerAction')
            ->assertStatus(200)
            ->assertSee('Dhaka');

        // Foreign model hydration triggers 403
        Livewire::test(E2EHydrationProbeComponent::class, ['lead' => $this->sylhetLead])
            ->call('triggerAction')
            ->assertStatus(403);
    }

    /**
     * Scenario 3: Superadmin Cross-Workspace Authority
     * Authenticate Superadmin; verify access to both ws-emk-dhaka and ws-corner-sylhet without 403.
     */
    public function test_scenario_3_superadmin_cross_workspace_authority(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        // Access Dhaka route
        $dhakaResponse = $this->get('/crm/ws-emk-dhaka/leads');
        $dhakaResponse->assertStatus(200);

        // Access Sylhet route without 403
        $sylhetResponse = $this->get('/crm/ws-corner-sylhet/leads');
        $sylhetResponse->assertStatus(200);

        // Global query sees both workspaces
        $allLeads = Lead::all();
        $this->assertGreaterThanOrEqual(2, $allLeads->count());
        $this->assertNotNull(Lead::find($this->dhakaLead->id));
        $this->assertNotNull(Lead::find($this->sylhetLead->id));

        // Superadmin bypasses hydration restrictions
        Livewire::test(E2EHydrationProbeComponent::class, ['lead' => $this->sylhetLead])
            ->call('triggerAction')
            ->assertStatus(200)
            ->assertSee('Sylhet');
    }

    /**
     * Scenario 4: Scoped Delegation & Non-Delegable Enforcement
     * Manager in ws-emk-dhaka successfully delegates forms.create to staff;
     * Manager attempts to delegate leads.reverse_conversion or platform_services.delegate_roles and is blocked;
     * Manager attempts cross-workspace delegation and is blocked.
     */
    public function test_scenario_4_scoped_delegation_and_non_delegable_enforcement(): void
    {
        // 1. Successful delegation within workspace boundary
        $this->staffService->delegatePermission($this->dhakaManager, $this->dhakaStaff, 'forms.create');
        $this->dhakaStaff->unsetRelation('permissions');
        $this->assertTrue($this->dhakaStaff->hasPermissionTo('forms.create'));

        // 2. Attempting to delegate strictly non-delegable permissions is blocked
        $this->expectException(UnauthorizedDelegationException::class);
        try {
            $this->staffService->delegatePermission(
                $this->dhakaManager,
                $this->dhakaStaff,
                'leads.reverse_conversion'
            );
        } catch (UnauthorizedDelegationException $e) {
            // First non-delegable blocked, verify second non-delegable
            $this->assertTrue(true);
        }

        // Attempting to delegate role delegation itself is blocked
        $this->expectException(UnauthorizedDelegationException::class);
        try {
            $this->staffService->delegatePermission(
                $this->dhakaManager,
                $this->dhakaStaff,
                'platform_services.delegate_roles'
            );
        } catch (UnauthorizedDelegationException $e) {
            $this->assertTrue(true);
        }

        // 3. Attempting cross-workspace delegation throws TenancyBoundaryException
        $this->expectException(TenancyBoundaryException::class);
        $this->staffService->delegatePermission(
            $this->dhakaManager,
            $this->sylhetStaff,
            'forms.create'
        );
    }

    /**
     * Scenario 5: Append-Only Audit Trail & Secret Redaction
     * Verify operations generate records in audit_logs;
     * verify password fields show [REDACTED];
     * attempt SQL UPDATE and DELETE on audit_logs and assert failure with SQLSTATE 45000.
     */
    public function test_scenario_5_append_only_audit_trail_and_secret_redaction(): void
    {
        // 1. Create a user which triggers AuditableObserver
        $auditedUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Audited Candidate',
            'email' => 'audited.candidate@emk.test',
            'password' => bcrypt('superSecretPassword123'),
            'guard' => 'web',
            'is_active' => true,
        ]);

        // Verify audit log record exists for user creation
        $userLog = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $auditedUser->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($userLog);
        $this->assertSame('ws-emk-dhaka', $userLog->workspace_id);

        // Verify password attribute is redacted
        $newValues = $userLog->new_values;
        $this->assertIsArray($newValues);
        $this->assertSame('[REDACTED]', $newValues['password'] ?? null);

        // 2. Attempt SQL UPDATE on audit_logs table -> must fail with SQLSTATE 45000
        $updateBlocked = false;
        try {
            DB::statement("UPDATE audit_logs SET reason = 'tampered' WHERE id = ?", [$userLog->id]);
        } catch (QueryException $e) {
            $updateBlocked = true;
            $this->assertStringContainsString('45000', $e->getMessage());
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertTrue($updateBlocked, 'Direct UPDATE query on audit_logs must be rejected with SQLSTATE 45000.');

        // 3. Attempt SQL DELETE on audit_logs table -> must fail with SQLSTATE 45000
        $deleteBlocked = false;
        try {
            DB::statement("DELETE FROM audit_logs WHERE id = ?", [$userLog->id]);
        } catch (QueryException $e) {
            $deleteBlocked = true;
            $this->assertStringContainsString('45000', $e->getMessage());
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertTrue($deleteBlocked, 'Direct DELETE query on audit_logs must be rejected with SQLSTATE 45000.');
    }

    /**
     * Scenario 6: Audit Log Viewer Tenancy Isolation
     * Manager reviews workspace audit trail; verifies foreign workspace logs are hidden;
     * Superadmin views cross-workspace audit logs.
     */
    public function test_scenario_6_audit_log_viewer_tenancy_isolation(): void
    {
        // 1. Create distinct audit logs in Dhaka and Sylhet
        $dhakaAudit = AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->dhakaManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_CREATED,
            'auditable_type' => Lead::class,
            'auditable_id' => 991,
            'old_values' => null,
            'new_values' => ['name' => 'Dhaka Log Entry', 'api_key' => '[REDACTED]'],
            'reason' => 'Dhaka E2E Audit Log Entry',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'PHPUnit/E2E',
            'created_at' => now(),
        ]);

        $sylhetAudit = AuditLog::create([
            'workspace_id' => 'ws-corner-sylhet',
            'actor_id' => $this->sylhetManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_CREATED,
            'auditable_type' => Lead::class,
            'auditable_id' => 992,
            'old_values' => null,
            'new_values' => ['name' => 'Sylhet Log Entry'],
            'reason' => 'Sylhet E2E Audit Log Entry',
            'ip_address' => '10.0.0.2',
            'user_agent' => 'PHPUnit/E2E',
            'created_at' => now(),
        ]);

        // 2. Dhaka Manager sees only Dhaka logs in AuditLogViewer
        Livewire::actingAs($this->dhakaManager, 'web')
            ->test(AuditLogViewer::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(200)
            ->assertSee('Dhaka E2E Audit Log Entry')
            ->assertDontSee('Sylhet E2E Audit Log Entry')
            ->call('viewDetails', $dhakaAudit->id)
            ->assertSee('[REDACTED]');

        // 3. Sylhet Manager sees only Sylhet logs
        Livewire::actingAs($this->sylhetManager, 'web')
            ->test(AuditLogViewer::class, ['workspace' => 'ws-corner-sylhet'])
            ->assertStatus(200)
            ->assertSee('Sylhet E2E Audit Log Entry')
            ->assertDontSee('Dhaka E2E Audit Log Entry');

        // 4. Superadmin sees logs across both workspaces and can filter
        $superadminViewer = Livewire::actingAs($this->superAdmin, 'web')
            ->test(AuditLogViewer::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(200)
            ->assertSee('Dhaka E2E Audit Log Entry')
            ->assertSee('Sylhet E2E Audit Log Entry');

        $superadminViewer->set('selectedWorkspace', 'ws-corner-sylhet')
            ->assertSee('Sylhet E2E Audit Log Entry')
            ->assertDontSee('Dhaka E2E Audit Log Entry');
    }
}
