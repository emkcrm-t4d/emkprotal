<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaManager;
    protected User $sylhetManager;
    protected User $superAdmin;
    protected Lead $lead;

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

        // 3. Seed users
        $this->dhakaManager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'manager.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaManager->assignRole('workspace_manager');

        $this->sylhetManager = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Manager',
            'email' => 'manager.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetManager->assignRole('workspace_manager');

        $this->superAdmin = User::create([
            'workspace_id' => null,
            'name' => 'Global Superadmin',
            'email' => 'superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        $this->lead = Lead::withoutGlobalScopes()->create([
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXIDDHK201',
            'first_name' => 'Rahim',
            'last_name' => 'Uddin',
            'email' => 'rahim@example.com',
            'contact_number' => '+8801700000000',
            'date_of_birth' => '1998-03-15',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '12/A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'BSc',
            'area_of_subject' => 'Physics',
            'submitted_at' => now(),
        ]);
    }

    public function test_audit_log_can_be_persisted_and_retrieved_via_eloquent(): void
    {
        $log = AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->dhakaManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_CREATED,
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
            'old_values' => null,
            'new_values' => ['first_name' => 'Rahim', 'status' => 'submitted'],
            'reason' => 'Initial lead intake record',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/11',
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'workspace_id' => 'ws-emk-dhaka',
            'event_type' => AuditLog::EVENT_CREATED,
        ]);

        $retrieved = AuditLog::withoutGlobalScopes()->find($log->id);
        $this->assertNotNull($retrieved);
        $this->assertSame('ws-emk-dhaka', $retrieved->getWorkspaceId());
        $this->assertSame(AuditLog::EVENT_CREATED, $retrieved->event_type);
        $this->assertNull($retrieved->updated_at);
    }

    public function test_old_values_and_new_values_cast_to_and_from_arrays(): void
    {
        $old = ['status' => 'submitted', 'score' => 50];
        $new = ['status' => 'verified', 'score' => 95];

        $log = AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->dhakaManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_UPDATED,
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => 'Score updated after review',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now(),
        ]);

        $fresh = AuditLog::withoutGlobalScopes()->find($log->id);
        $this->assertIsArray($fresh->old_values);
        $this->assertIsArray($fresh->new_values);
        $this->assertSame('submitted', $fresh->old_values['status']);
        $this->assertSame('verified', $fresh->new_values['status']);
        $this->assertSame(95, $fresh->new_values['score']);
    }

    public function test_actor_workspace_and_auditable_relationships_resolve(): void
    {
        $log = AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->dhakaManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_DOCUMENT_ACCESSED,
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
            'old_values' => null,
            'new_values' => null,
            'reason' => 'Relationship verification',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestBrowser',
            'created_at' => now(),
        ]);

        $fresh = AuditLog::withoutGlobalScopes()->find($log->id);
        $this->assertInstanceOf(User::class, $fresh->actor);
        $this->assertSame($this->dhakaManager->id, $fresh->actor->id);

        $this->assertNotNull($fresh->workspace);
        $this->assertSame('ws-emk-dhaka', $fresh->workspace->id);

        $this->assertInstanceOf(Lead::class, $fresh->auditable);
        $this->assertSame($this->lead->id, $fresh->auditable->id);
    }

    public function test_tenant_scope_filters_audit_logs_for_workspace_staff(): void
    {
        // Insert Dhaka log
        AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->dhakaManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_CREATED,
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
            'old_values' => null,
            'new_values' => ['first_name' => 'Rahim'],
            'ip_address' => '127.0.0.1',
        ]);

        // Insert Sylhet log
        AuditLog::create([
            'workspace_id' => 'ws-corner-sylhet',
            'actor_id' => $this->sylhetManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_CREATED,
            'auditable_type' => User::class,
            'auditable_id' => $this->sylhetManager->id,
            'old_values' => null,
            'new_values' => ['name' => 'Sylhet Manager'],
            'ip_address' => '127.0.0.1',
        ]);

        // When acting as Dhaka Manager, only Dhaka logs are visible
        $this->actingAs($this->dhakaManager);
        $dhakaLogs = AuditLog::all();
        $this->assertCount(1, $dhakaLogs);
        $this->assertSame('ws-emk-dhaka', $dhakaLogs->first()->workspace_id);

        // When acting as Sylhet Manager, only Sylhet logs are visible
        $this->actingAs($this->sylhetManager);
        $sylhetLogs = AuditLog::all();
        $this->assertCount(1, $sylhetLogs);
        $this->assertSame('ws-corner-sylhet', $sylhetLogs->first()->workspace_id);

        // When acting as Superadmin, all logs are visible
        $this->actingAs($this->superAdmin);
        $allLogs = AuditLog::all();
        $this->assertCount(2, $allLogs);
    }
}
