<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Livewire\Audit\AuditLogViewer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaManager;
    protected User $sylhetManager;
    protected User $unauthorizedStaff;
    protected User $superAdmin;
    protected AuditLog $dhakaLog;
    protected AuditLog $sylhetLog;

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

        // 3. Seed roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        // 4. Create users
        $this->dhakaManager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'viewer.manager.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaManager->assignRole('workspace_manager');

        $this->sylhetManager = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Manager',
            'email' => 'viewer.manager.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetManager->assignRole('workspace_manager');

        $this->unauthorizedStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Delegated Staff Without Audit Perms',
            'email' => 'viewer.staff.noaudit@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->unauthorizedStaff->assignRole('delegated_staff');

        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'viewer.superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 5. Create audit logs in both workspaces
        $this->dhakaLog = AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->dhakaManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_CREATED,
            'auditable_type' => Lead::class,
            'auditable_id' => 101,
            'old_values' => null,
            'new_values' => [
                'name' => 'Dhaka Candidate',
                'password' => '[REDACTED]',
                'token' => '[REDACTED]',
            ],
            'reason' => 'Dhaka intake audit log entry',
            'ip_address' => '192.168.1.10',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now()->subMinutes(10),
        ]);

        $this->sylhetLog = AuditLog::create([
            'workspace_id' => 'ws-corner-sylhet',
            'actor_id' => $this->sylhetManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_UPDATED,
            'auditable_type' => Lead::class,
            'auditable_id' => 202,
            'old_values' => ['status' => 'submitted'],
            'new_values' => ['status' => 'verified'],
            'reason' => 'Sylhet intake verification entry',
            'ip_address' => '192.168.2.20',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now()->subMinutes(5),
        ]);
    }

    public function test_staff_with_platform_services_view_audit_logs_can_access_route(): void
    {
        $response = $this->actingAs($this->dhakaManager, 'web')
            ->get('/crm/ws-emk-dhaka/audit-logs');

        $response->assertStatus(200);
        $response->assertSee('Audit Logs');
        $response->assertSee('ws-emk-dhaka');
    }

    public function test_staff_without_platform_services_view_audit_logs_receives_http_403(): void
    {
        $response = $this->actingAs($this->unauthorizedStaff, 'web')
            ->get('/crm/ws-emk-dhaka/audit-logs');

        $response->assertStatus(403);
    }

    public function test_workspace_manager_sees_only_records_for_their_workspace(): void
    {
        Livewire::actingAs($this->dhakaManager, 'web')
            ->test(AuditLogViewer::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(200)
            ->assertSee('Dhaka intake audit log entry')
            ->assertDontSee('Sylhet intake verification entry');

        Livewire::actingAs($this->sylhetManager, 'web')
            ->test(AuditLogViewer::class, ['workspace' => 'ws-corner-sylhet'])
            ->assertStatus(200)
            ->assertSee('Sylhet intake verification entry')
            ->assertDontSee('Dhaka intake audit log entry');
    }

    public function test_superadmin_can_view_and_filter_records_across_workspaces(): void
    {
        // Unfiltered Superadmin view sees records from both workspaces
        $test = Livewire::actingAs($this->superAdmin, 'web')
            ->test(AuditLogViewer::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(200)
            ->assertSee('Dhaka intake audit log entry')
            ->assertSee('Sylhet intake verification entry');

        // Filter by ws-corner-sylhet
        $test->set('selectedWorkspace', 'ws-corner-sylhet')
            ->assertSee('Sylhet intake verification entry')
            ->assertDontSee('Dhaka intake audit log entry');

        // Filter by ws-emk-dhaka
        $test->set('selectedWorkspace', 'ws-emk-dhaka')
            ->assertSee('Dhaka intake audit log entry')
            ->assertDontSee('Sylhet intake verification entry');
    }

    public function test_redacted_attributes_display_as_redacted_in_the_view_modal(): void
    {
        Livewire::actingAs($this->dhakaManager, 'web')
            ->test(AuditLogViewer::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(200)
            ->call('viewDetails', $this->dhakaLog->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('[REDACTED]')
            ->assertSee('Dhaka Candidate');
    }

    public function test_workspace_manager_cannot_access_foreign_workspace_audit_logs(): void
    {
        $response = $this->actingAs($this->dhakaManager, 'web')
            ->get('/crm/ws-corner-sylhet/audit-logs');

        $response->assertStatus(403);
    }

    public function test_audit_logs_can_be_filtered_by_search_query_and_event_type(): void
    {
        Livewire::actingAs($this->dhakaManager, 'web')
            ->test(AuditLogViewer::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSee('Dhaka intake audit log entry')
            ->set('search', 'NonExistentSearchTermXYZ')
            ->assertDontSee('Dhaka intake audit log entry')
            ->set('search', 'Dhaka intake')
            ->assertSee('Dhaka intake audit log entry')
            ->set('eventType', AuditLog::EVENT_DELETED)
            ->assertDontSee('Dhaka intake audit log entry')
            ->set('eventType', AuditLog::EVENT_CREATED)
            ->assertSee('Dhaka intake audit log entry');
    }
}
