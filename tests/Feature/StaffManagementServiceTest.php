<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\AccessControl\Exceptions\TenancyBoundaryException;
use App\Domain\AccessControl\Exceptions\UnauthorizedDelegationException;
use App\Domain\AccessControl\Services\DelegationPolicyService;
use App\Domain\AccessControl\Services\StaffManagementService;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StaffManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StaffManagementService $service;
    protected User $managerDhaka;
    protected User $managerSylhet;
    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $sylhetSpace = \Illuminate\Support\Facades\DB::table('spaces')->where('code', 'CR-SYL')->first();
        \Illuminate\Support\Facades\DB::table('workspaces')->updateOrInsert(
            ['id' => 'ws-corner-sylhet'],
            [
                'space_id' => $sylhetSpace ? $sylhetSpace->id : 1,
                'name' => 'American Corner Sylhet',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->service = app(StaffManagementService::class);

        $this->managerDhaka = User::where('email', 'manager.dhaka@emk.test')->first();

        $this->managerSylhet = User::create([
            'name' => 'Sylhet Workspace Manager',
            'email' => 'manager.sylhet@emk.test',
            'workspace_id' => 'ws-corner-sylhet',
            'password' => Hash::make('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->managerSylhet->assignRole('workspace_manager');

        $this->superadmin = User::where('email', 'admin@emk.test')->first();
    }

    public function test_create_staff_user_provisions_record_with_defaults_and_delegated_staff_role(): void
    {
        $staff = $this->service->createStaffUser([
            'name' => 'John Desk Staff',
            'email' => 'john.staff@emk.test',
            'password' => 'tempPassword123!',
        ], $this->managerDhaka);

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'email' => 'john.staff@emk.test',
            'name' => 'John Desk Staff',
            'workspace_id' => 'ws-emk-dhaka',
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => true,
        ]);

        $this->assertTrue($staff->hasRole('delegated_staff'));
        $this->assertTrue($staff->must_reset_password);
        $this->assertTrue($staff->is_active);
    }

    public function test_create_staff_user_enforces_manager_workspace_id(): void
    {
        // Even if manager supplies a different workspace_id in payload, manager's workspace_id is enforced
        $staff = $this->service->createStaffUser([
            'name' => 'Workspace Bound Staff',
            'email' => 'bound.staff@emk.test',
            'workspace_id' => 'ws-corner-sylhet',
        ], $this->managerDhaka);

        $this->assertEquals('ws-emk-dhaka', $staff->workspace_id);
    }

    public function test_superadmin_can_provision_staff_for_explicit_workspace(): void
    {
        $staff = $this->service->createStaffUser([
            'name' => 'Sylhet Staff by Superadmin',
            'email' => 'sylhet.staff@emk.test',
            'workspace_id' => 'ws-corner-sylhet',
        ], $this->superadmin);

        $this->assertEquals('ws-corner-sylhet', $staff->workspace_id);
    }

    public function test_create_staff_user_rejects_duplicate_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User with email [manager.dhaka@emk.test] already exists.');

        $this->service->createStaffUser([
            'name' => 'Duplicate Email Staff',
            'email' => 'manager.dhaka@emk.test',
        ], $this->managerDhaka);
    }

    public function test_delegate_permission_assigns_permission_and_clears_cache(): void
    {
        $cacheKey = config('permission.cache.key', 'spatie.permission.cache');
        \Illuminate\Support\Facades\Cache::put($cacheKey, 'test_cached_data');
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($cacheKey));

        $staff = $this->service->createStaffUser([
            'name' => 'Jane Delegated',
            'email' => 'jane.delegated@emk.test',
        ], $this->managerDhaka);

        $this->assertFalse($staff->hasDirectPermission('forms.create'));

        \Illuminate\Support\Facades\Cache::put($cacheKey, 'test_cached_data_pre_delegate');
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($cacheKey));

        $this->service->delegatePermission($this->managerDhaka, $staff, 'forms.create');

        // Assert cache key was evicted immediately by delegatePermission
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has($cacheKey));

        $staff->refresh();
        $this->assertTrue($staff->hasDirectPermission('forms.create'));
        $this->assertTrue($staff->hasPermissionTo('forms.create', 'web'));
    }

    public function test_revoke_delegated_permission_removes_permission_and_clears_cache(): void
    {
        $cacheKey = config('permission.cache.key', 'spatie.permission.cache');

        $staff = $this->service->createStaffUser([
            'name' => 'Mark Revokable',
            'email' => 'mark.revokable@emk.test',
        ], $this->managerDhaka);

        $this->service->delegatePermission($this->managerDhaka, $staff, 'members.edit');
        $this->assertTrue($staff->hasDirectPermission('members.edit'));

        \Illuminate\Support\Facades\Cache::put($cacheKey, 'test_cached_data_pre_revoke');
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($cacheKey));

        $this->service->revokeDelegatedPermission($this->managerDhaka, $staff, 'members.edit');

        // Assert cache key was evicted immediately by revokeDelegatedPermission
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has($cacheKey));

        $staff->refresh();
        $this->assertFalse($staff->hasDirectPermission('members.edit'));
    }

    public function test_attempting_unauthorized_delegation_throws_exception_and_leaves_permissions_unchanged(): void
    {
        $staff = $this->service->createStaffUser([
            'name' => 'Protected Staff',
            'email' => 'protected.staff@emk.test',
        ], $this->managerDhaka);

        $this->expectException(UnauthorizedDelegationException::class);

        try {
            $this->service->delegatePermission($this->managerDhaka, $staff, 'leads.reverse_conversion');
        } finally {
            $staff->refresh();
            $this->assertFalse($staff->hasPermissionTo('leads.reverse_conversion', 'web'));
        }
    }

    public function test_attempting_cross_workspace_delegation_throws_tenancy_boundary_exception(): void
    {
        $staffDhaka = $this->service->createStaffUser([
            'name' => 'Dhaka Staff Member',
            'email' => 'dhaka.staff@emk.test',
        ], $this->managerDhaka);

        $this->expectException(TenancyBoundaryException::class);

        try {
            $this->service->delegatePermission($this->managerSylhet, $staffDhaka, 'forms.create');
        } finally {
            $staffDhaka->refresh();
            $this->assertFalse($staffDhaka->hasPermissionTo('forms.create', 'web'));
        }
    }

    public function test_attempting_cross_workspace_revocation_throws_tenancy_boundary_exception(): void
    {
        $staffDhaka = $this->service->createStaffUser([
            'name' => 'Dhaka Revoke Target',
            'email' => 'dhaka.target@emk.test',
        ], $this->managerDhaka);

        $this->service->delegatePermission($this->managerDhaka, $staffDhaka, 'invoices.export');

        $this->expectException(TenancyBoundaryException::class);
        $this->service->revokeDelegatedPermission($this->managerSylhet, $staffDhaka, 'invoices.export');
    }

    public function test_deactivate_and_activate_staff_user(): void
    {
        $cacheKey = config('permission.cache.key', 'spatie.permission.cache');

        $staff = $this->service->createStaffUser([
            'name' => 'Active Status Staff',
            'email' => 'status.staff@emk.test',
        ], $this->managerDhaka);

        $this->assertTrue($staff->is_active);

        \Illuminate\Support\Facades\Cache::put($cacheKey, 'test_cached_data_pre_deactivate');
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($cacheKey));

        // Deactivate
        $this->service->deactivateStaffUser($this->managerDhaka, $staff);
        $staff->refresh();
        $this->assertFalse($staff->is_active);
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has($cacheKey));

        \Illuminate\Support\Facades\Cache::put($cacheKey, 'test_cached_data_pre_activate');
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has($cacheKey));

        // Activate
        $this->service->activateStaffUser($this->managerDhaka, $staff);
        $staff->refresh();
        $this->assertTrue($staff->is_active);
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has($cacheKey));
    }

    public function test_cross_workspace_deactivation_is_blocked_with_tenancy_boundary_exception(): void
    {
        $staffDhaka = $this->service->createStaffUser([
            'name' => 'Dhaka Staff Target',
            'email' => 'dhaka.deactivate@emk.test',
        ], $this->managerDhaka);

        $this->expectException(TenancyBoundaryException::class);
        $this->service->deactivateStaffUser($this->managerSylhet, $staffDhaka);
    }
}
