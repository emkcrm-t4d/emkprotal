<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VerifyWorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaStaff;
    protected User $unassignedStaff;
    protected User $superAdmin;
    protected User $globalAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed spaces
        DB::table('spaces')->insert([
            ['id' => 1, 'code' => 'EMK-DHK', 'name' => 'EMK Center Dhaka', 'category' => 'Center', 'division' => 'Dhaka', 'district' => 'Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'CR-SYL', 'name' => 'American Corner Sylhet', 'category' => 'Corner', 'division' => 'Sylhet', 'district' => 'Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Seed workspaces
        DB::table('workspaces')->insert([
            ['id' => 'ws-emk-dhaka', 'space_id' => 1, 'name' => 'EMK Center Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'ws-corner-sylhet', 'space_id' => 2, 'name' => 'American Corner Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // 3. Staff in ws-emk-dhaka
        $this->dhakaStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Staff',
            'email' => 'staff.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaStaff->assignRole('workspace_manager');

        // 4. Staff with null workspace_id
        $this->unassignedStaff = User::create([
            'workspace_id' => null,
            'name' => 'Unassigned Staff',
            'email' => 'unassigned@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->unassignedStaff->assignRole('workspace_manager');

        // 5. Superadmin user
        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 6. Global Admin user
        $this->globalAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Global Administrator',
            'email' => 'globaladmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->globalAdmin->assignRole('global_admin');
    }

    public function test_staff_in_assigned_workspace_accessing_leads_receives_200_ok(): void
    {
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get('/crm/ws-emk-dhaka/leads');

        $response->assertOk()
            ->assertSee('Inbound Leads');
    }

    public function test_staff_in_assigned_workspace_accessing_baseline_routes_receives_200_ok(): void
    {
        $modules = ['members', 'invoices', 'reports'];

        foreach ($modules as $module) {
            $response = $this->actingAs($this->dhakaStaff, 'web')
                ->getJson("/crm/ws-emk-dhaka/{$module}");

            $response->assertOk()
                ->assertJson([
                    'status' => 'ok',
                    'workspace' => 'ws-emk-dhaka',
                    'module' => $module,
                ]);
        }
    }

    public function test_staff_in_assigned_workspace_accessing_mismatched_workspace_receives_403_forbidden(): void
    {
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->getJson('/crm/ws-corner-sylhet/leads');

        $response->assertStatus(403);
    }

    public function test_staff_with_null_workspace_id_receives_403_forbidden(): void
    {
        $response = $this->actingAs($this->unassignedStaff, 'web')
            ->getJson('/crm/ws-emk-dhaka/leads');

        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_any_workspace_leads(): void
    {
        $responseDhaka = $this->actingAs($this->superAdmin, 'web')
            ->get('/crm/ws-emk-dhaka/leads');

        $responseDhaka->assertOk()
            ->assertSee('Inbound Leads');

        $responseSylhet = $this->actingAs($this->superAdmin, 'web')
            ->get('/crm/ws-corner-sylhet/leads');

        $responseSylhet->assertOk()
            ->assertSee('Inbound Leads');
    }

    public function test_global_admin_can_access_any_workspace_leads(): void
    {
        $responseDhaka = $this->actingAs($this->globalAdmin, 'web')
            ->get('/crm/ws-emk-dhaka/leads');

        $responseDhaka->assertOk()
            ->assertSee('Inbound Leads');

        $responseSylhet = $this->actingAs($this->globalAdmin, 'web')
            ->get('/crm/ws-corner-sylhet/leads');

        $responseSylhet->assertOk()
            ->assertSee('Inbound Leads');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $jsonResponse = $this->getJson('/crm/ws-emk-dhaka/leads');
        $jsonResponse->assertStatus(401);

        $htmlResponse = $this->get('/crm/ws-emk-dhaka/leads');
        $this->assertTrue(
            $htmlResponse->status() === 401 || $htmlResponse->isRedirect(route('login'))
        );
    }

    public function test_malformed_workspace_slug_is_rejected_with_403_or_404(): void
    {
        $malformedSlugs = [
            'ws-emk-dhaka\' OR 1=1--',
            'ws_with_underscores',
            'ws@special!chars',
            '../../traversal',
            'WS-CAPS',
        ];

        foreach ($malformedSlugs as $slug) {
            $response = $this->actingAs($this->dhakaStaff, 'web')
                ->get('/crm/' . urlencode($slug) . '/leads');

            $this->assertTrue(
                in_array($response->status(), [403, 404], true),
                "Expected 403 or 404 for slug '{$slug}', received {$response->status()}"
            );
        }
    }
}
