<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiGuardAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_roles_and_permissions_are_correctly_seeded(): void
    {
        // Assert canonical roles exist
        $this->assertTrue(Role::where('name', 'superadmin')->where('guard_name', 'web')->exists());
        $this->assertTrue(Role::where('name', 'global_admin')->where('guard_name', 'web')->exists());
        $this->assertTrue(Role::where('name', 'workspace_manager')->where('guard_name', 'web')->exists());
        $this->assertTrue(Role::where('name', 'delegated_staff')->where('guard_name', 'web')->exists());
        $this->assertTrue(Role::where('name', 'member')->where('guard_name', 'member')->exists());

        // Assert declared permissions exist across web and member guards
        $this->assertTrue(Permission::where('name', 'leads.convert')->where('guard_name', 'web')->exists());
        $this->assertTrue(Permission::where('name', 'leads.reverse_conversion')->where('guard_name', 'web')->exists());
        $this->assertTrue(Permission::where('name', 'platform_services.view_audit_logs')->where('guard_name', 'web')->exists());
        $this->assertTrue(Permission::where('name', 'member_portal.view_profile')->where('guard_name', 'member')->exists());

        // Assert role permission matrices
        $managerRole = Role::findByName('workspace_manager', 'web');
        $this->assertTrue($managerRole->hasPermissionTo('leads.approve'));
        $this->assertTrue($managerRole->hasPermissionTo('leads.reverse_conversion'));
        $this->assertTrue($managerRole->hasPermissionTo('platform_services.delegate_roles'));

        $staffRole = Role::findByName('delegated_staff', 'web');
        $this->assertTrue($staffRole->hasPermissionTo('leads.view'));
        $this->assertTrue($staffRole->hasPermissionTo('forms.view'));
        $this->assertFalse($staffRole->hasPermissionTo('leads.approve'));
        $this->assertFalse($staffRole->hasPermissionTo('leads.reverse_conversion'));

        $memberRole = Role::findByName('member', 'member');
        $this->assertTrue($memberRole->hasPermissionTo('member_portal.view_profile'));
        $this->assertTrue($memberRole->hasPermissionTo('member_portal.request_renewal'));

        // Assert default seeded administrative users
        $superadmin = User::where('email', 'admin@emk.test')->first();
        $this->assertNotNull($superadmin);
        $this->assertTrue($superadmin->hasRole('superadmin'));

        $manager = User::where('email', 'manager.dhaka@emk.test')->first();
        $this->assertNotNull($manager);
        $this->assertTrue($manager->hasRole('workspace_manager'));
    }

    public function test_user_with_must_reset_password_true_is_blocked_from_protected_workspace_routes(): void
    {
        $user = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Pending Password Reset Staff',
            'email' => 'reset.required@emk.test',
            'password' => Hash::make('temp-pass-123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => true,
        ]);
        $user->assignRole('workspace_manager');

        // HTML request redirects to password reset
        $htmlResponse = $this->actingAs($user, 'web')
            ->get('/crm/ws-emk-dhaka/tracer-check');

        $htmlResponse->assertRedirect(route('password.reset-required'));

        // JSON / API request receives HTTP 403 Forbidden with flag
        $jsonResponse = $this->actingAs($user, 'web')
            ->getJson('/crm/ws-emk-dhaka/tracer-check');

        $jsonResponse->assertStatus(403)
            ->assertJson([
                'password_reset_required' => true,
            ]);
    }

    public function test_once_password_is_reset_user_can_proceed_normally(): void
    {
        $user = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Active Staff',
            'email' => 'staff.active@emk.test',
            'password' => Hash::make('new-secret-123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $user->assignRole('workspace_manager');

        $response = $this->actingAs($user, 'web')
            ->getJson('/crm/ws-emk-dhaka/tracer-check');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'workspace_id' => 'ws-emk-dhaka',
            ]);
    }

    public function test_guard_isolation_web_staff_cannot_authenticate_as_member_guard(): void
    {
        $staffUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Staff',
            'email' => 'staff.only@emk.test',
            'password' => Hash::make('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);

        // Attempting to authenticate web staff under member guard fails
        $authenticated = Auth::guard('member')->attempt([
            'email' => 'staff.only@emk.test',
            'password' => 'secret123',
        ]);

        $this->assertFalse($authenticated, 'Web staff must not authenticate under member guard');
    }

    public function test_guard_isolation_member_session_cannot_access_web_crm_routes(): void
    {
        $memberUser = MemberUser::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Portal Member',
            'email' => 'member.portal@emk.test',
            'password' => Hash::make('secret123'),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $memberUser->assignRole('member');

        // Member authenticated under member guard attempts to access web guard route
        $response = $this->actingAs($memberUser, 'member')
            ->getJson('/crm/ws-emk-dhaka/tracer-check');

        // auth:web rejects the request because member guard is unauthenticated under web
        $this->assertTrue(in_array($response->status(), [401, 403], true));
    }
}
