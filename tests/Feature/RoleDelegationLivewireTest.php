<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\AccessControl\Services\DelegationPolicyService;
use App\Domain\AccessControl\Services\StaffManagementService;
use App\Livewire\Staff\RoleDelegation;
use App\Livewire\Staff\StaffDirectory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RoleDelegationLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $managerDhaka;
    protected User $managerSylhet;
    protected User $staffDhaka1;
    protected User $staffDhaka2;
    protected User $staffSylhet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // Seed Sylhet workspace
        $sylhetSpace = DB::table('spaces')->where('code', 'CR-SYL')->first();
        DB::table('workspaces')->updateOrInsert(
            ['id' => 'ws-corner-sylhet'],
            [
                'space_id' => $sylhetSpace ? $sylhetSpace->id : 1,
                'name' => 'American Corner Sylhet',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->managerDhaka = User::where('email', 'manager.dhaka@emk.test')->first();

        $this->managerSylhet = User::create([
            'name' => 'Sylhet Manager',
            'email' => 'manager.sylhet@emk.test',
            'workspace_id' => 'ws-corner-sylhet',
            'password' => Hash::make('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->managerSylhet->assignRole('workspace_manager');

        $staffService = app(StaffManagementService::class);

        $this->staffDhaka1 = $staffService->createStaffUser([
            'name' => 'Dhaka Staff Alpha',
            'email' => 'dhaka.alpha@emk.test',
        ], $this->managerDhaka);

        $this->staffDhaka2 = $staffService->createStaffUser([
            'name' => 'Dhaka Staff Beta',
            'email' => 'dhaka.beta@emk.test',
        ], $this->managerDhaka);

        $this->staffSylhet = $staffService->createStaffUser([
            'name' => 'Sylhet Staff Gamma',
            'email' => 'sylhet.gamma@emk.test',
            'workspace_id' => 'ws-corner-sylhet',
        ], $this->managerSylhet);
    }

    public function test_staff_directory_renders_workspace_staff_and_hides_other_workspaces(): void
    {
        $this->actingAs($this->managerDhaka);

        Livewire::test(StaffDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(200)
            ->assertSee('Dhaka Staff Alpha')
            ->assertSee('dhaka.alpha@emk.test')
            ->assertSee('Dhaka Staff Beta')
            ->assertSee('dhaka.beta@emk.test')
            ->assertDontSee('Sylhet Staff Gamma')
            ->assertDontSee('sylhet.gamma@emk.test');
    }

    public function test_staff_directory_rejects_cross_workspace_manager(): void
    {
        $this->actingAs($this->managerDhaka);

        // Dhaka manager attempting to view Sylhet staff directory
        Livewire::test(StaffDirectory::class, ['workspace' => 'ws-corner-sylhet'])
            ->assertStatus(403);
    }

    public function test_staff_directory_toggle_activation(): void
    {
        $this->actingAs($this->managerDhaka);

        $this->assertTrue($this->staffDhaka1->is_active);

        Livewire::test(StaffDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('toggleActivation', $this->staffDhaka1->id)
            ->assertSee('deactivated');

        $this->staffDhaka1->refresh();
        $this->assertFalse($this->staffDhaka1->is_active);

        Livewire::test(StaffDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('toggleActivation', $this->staffDhaka1->id)
            ->assertSee('activated');

        $this->staffDhaka1->refresh();
        $this->assertTrue($this->staffDhaka1->is_active);
    }

    public function test_role_delegation_renders_delegable_and_locked_actions(): void
    {
        $this->actingAs($this->managerDhaka);

        $component = Livewire::test(RoleDelegation::class, [
            'workspace' => 'ws-emk-dhaka',
            'staffId' => $this->staffDhaka1->id,
        ]);

        $component->assertStatus(200)
            ->assertSee('Dhaka Staff Alpha')
            ->assertSee('Operational Actions (Delegable by Workspace Manager)')
            ->assertSee('Locked Managerial Actions (Strictly Non-Delegable)')
            ->assertSee('forms.create')
            ->assertSee('members.export_csv')
            ->assertSee('leads.reverse_conversion')
            ->assertSee('leads.approve')
            ->assertSee('leads.convert')
            ->assertSee('platform_services.delegate_roles')
            ->assertSee('Locked');
    }

    public function test_role_delegation_toggles_delegable_permission(): void
    {
        $this->actingAs($this->managerDhaka);

        $this->assertFalse($this->staffDhaka1->hasDirectPermission('forms.create'));

        $component = Livewire::test(RoleDelegation::class, [
            'workspace' => 'ws-emk-dhaka',
            'staffId' => $this->staffDhaka1->id,
        ]);

        // Grant permission
        $component->call('togglePermission', 'forms.create')
            ->assertSee('delegated successfully')
            ->assertSet('errorMessage', null);

        $this->staffDhaka1->refresh();
        $this->assertTrue($this->staffDhaka1->hasDirectPermission('forms.create'));

        // Revoke permission
        $component->call('togglePermission', 'forms.create')
            ->assertSee('revoked successfully')
            ->assertSet('errorMessage', null);

        $this->staffDhaka1->refresh();
        $this->assertFalse($this->staffDhaka1->hasDirectPermission('forms.create'));
    }

    public function test_role_delegation_rejects_non_delegable_action_with_error_banner(): void
    {
        $this->actingAs($this->managerDhaka);

        $component = Livewire::test(RoleDelegation::class, [
            'workspace' => 'ws-emk-dhaka',
            'staffId' => $this->staffDhaka1->id,
        ]);

        // Attempt to delegate strictly non-delegable action
        $component->call('togglePermission', 'leads.reverse_conversion')
            ->assertSee('Action leads.reverse_conversion is non-delegable to staff.')
            ->assertSet('statusMessage', null);

        $this->staffDhaka1->refresh();
        $this->assertFalse($this->staffDhaka1->hasPermissionTo('leads.reverse_conversion', 'web'));
    }

    public function test_role_delegation_rejects_cross_workspace_staff_hydration(): void
    {
        $this->actingAs($this->managerDhaka);

        // Manager Dhaka attempting to mount delegation component for Sylhet staff
        Livewire::test(RoleDelegation::class, [
            'workspace' => 'ws-emk-dhaka',
            'staffId' => $this->staffSylhet->id,
        ])->assertStatus(403);
    }

    public function test_staff_route_is_accessible_via_http(): void
    {
        $this->actingAs($this->managerDhaka);

        $response = $this->get('/crm/ws-emk-dhaka/staff');
        $response->assertStatus(200);
        $response->assertSee('Workspace Staff Directory');
        $response->assertSee('Dhaka Staff Alpha');
    }

    public function test_delegated_staff_cannot_access_staff_directory_route(): void
    {
        $this->staffDhaka1->must_reset_password = false;
        $this->staffDhaka1->save();

        $this->actingAs($this->staffDhaka1);

        $response = $this->get('/crm/ws-emk-dhaka/staff');
        $response->assertStatus(403);
    }
}
