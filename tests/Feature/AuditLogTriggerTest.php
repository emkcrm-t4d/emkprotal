<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogTriggerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test inserting an audit log record succeeds.
     */
    public function test_audit_logs_record_can_be_inserted(): void
    {
        $id = DB::table('audit_logs')->insertGetId([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => 1,
            'actor_guard' => 'web',
            'event_type' => 'lead_verified',
            'auditable_type' => 'App\\Models\\Lead',
            'auditable_id' => 10,
            'old_values' => json_encode(['status' => 'submitted']),
            'new_values' => json_encode(['status' => 'under_review']),
            'reason' => 'Initial review started',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/Test',
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $id,
            'workspace_id' => 'ws-emk-dhaka',
            'event_type' => 'lead_verified',
        ]);
    }

    /**
     * Test updating an audit log record throws QueryException with SQLSTATE 45000.
     */
    public function test_audit_logs_update_is_blocked_by_trigger_with_sqlstate_45000(): void
    {
        $id = DB::table('audit_logs')->insertGetId([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => 1,
            'actor_guard' => 'web',
            'event_type' => 'lead_verified',
            'auditable_type' => 'App\\Models\\Lead',
            'auditable_id' => 10,
            'old_values' => null,
            'new_values' => null,
            'reason' => 'Test trigger',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/Test',
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Audit logs are strictly append-only and cannot be modified or updated (DEC-11).');

        try {
            DB::table('audit_logs')->where('id', $id)->update([
                'reason' => 'Tampered reason',
            ]);
        } catch (QueryException $e) {
            $this->assertStringContainsString('45000', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test deleting an audit log record throws QueryException with SQLSTATE 45000.
     */
    public function test_audit_logs_delete_is_blocked_by_trigger_with_sqlstate_45000(): void
    {
        $id = DB::table('audit_logs')->insertGetId([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => 1,
            'actor_guard' => 'web',
            'event_type' => 'lead_verified',
            'auditable_type' => 'App\\Models\\Lead',
            'auditable_id' => 10,
            'old_values' => null,
            'new_values' => null,
            'reason' => 'Test trigger delete',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/Test',
            'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Audit logs are strictly append-only and cannot be deleted (DEC-11).');

        try {
            DB::table('audit_logs')->where('id', $id)->delete();
        } catch (QueryException $e) {
            $this->assertStringContainsString('45000', $e->getMessage());
            throw $e;
        }
    }
}
