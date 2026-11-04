<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditAuthenticationEventsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed space and workspace
        DB::table('spaces')->insert([
            'id' => 1,
            'code' => 'EMK-DHK',
            'name' => 'EMK Center Dhaka',
            'category' => 'Center',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspaces')->insert([
            'id' => 'ws-emk-dhaka',
            'space_id' => 1,
            'name' => 'EMK Center Dhaka',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Test User',
            'email' => 'user@emk.test',
            'password' => bcrypt('ValidPassword123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
    }

    public function test_successful_login_event_generates_audit_record(): void
    {
        event(new Login('web', $this->user, false));

        $log = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_LOGIN_SUCCESS)
            ->where('actor_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('ws-emk-dhaka', $log->workspace_id);
        $this->assertSame('web', $log->actor_guard);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertSame($this->user->id, $log->auditable_id);
        $this->assertSame('Successful authentication', $log->reason);
        $this->assertSame($this->user->email, $log->new_values['email']);
    }

    public function test_failed_login_event_records_audit_trail_without_password(): void
    {
        $submittedPassword = 'SuperSecretPlaintextPasswordNeverExpose123!';

        // Scenario A: Failed login for existing user
        event(new Failed('web', $this->user, [
            'email' => 'user@emk.test',
            'password' => $submittedPassword,
        ]));

        $log = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_LOGIN_FAILED)
            ->where('actor_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('ws-emk-dhaka', $log->workspace_id);
        $this->assertSame('web', $log->actor_guard);
        $this->assertSame('user@emk.test', $log->new_values['attempted_identity']);
        $this->assertStringContainsString('user@emk.test', (string) $log->reason);

        // Assert submitted password was completely omitted
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $rawLog = DB::table('audit_logs')->where('id', $log->id)->first();
        $this->assertStringNotContainsString($submittedPassword, json_encode($rawLog));

        // Scenario B: Failed login for non-existent user (null user object)
        $ghostPassword = 'AnotherSecretPassword999!';
        event(new Failed('web', null, [
            'email' => 'ghost@unknown.test',
            'password' => $ghostPassword,
        ]));

        $ghostLog = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_LOGIN_FAILED)
            ->whereNull('actor_id')
            ->latest('id')
            ->first();

        $this->assertNotNull($ghostLog);
        $this->assertNull($ghostLog->actor_id);
        $this->assertNull($ghostLog->auditable_id);
        $this->assertSame('ghost@unknown.test', $ghostLog->new_values['attempted_identity']);
        $rawGhostLog = DB::table('audit_logs')->where('id', $ghostLog->id)->first();
        $this->assertStringNotContainsString($ghostPassword, json_encode($rawGhostLog));
    }

    public function test_logout_event_generates_audit_record(): void
    {
        event(new Logout('web', $this->user));

        $log = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_LOGOUT)
            ->where('actor_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('ws-emk-dhaka', $log->workspace_id);
        $this->assertSame('web', $log->actor_guard);
        $this->assertSame('User logged out', $log->reason);
    }

    public function test_password_reset_event_generates_audit_record(): void
    {
        event(new PasswordReset($this->user));

        $log = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_PASSWORD_RESET)
            ->where('actor_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('ws-emk-dhaka', $log->workspace_id);
        $this->assertSame('Password reset completed', $log->reason);
    }

    public function test_database_triggers_prevent_updating_or_deleting_generated_auth_audit_logs(): void
    {
        event(new Login('web', $this->user, false));

        $log = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_LOGIN_SUCCESS)
            ->where('actor_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);

        // 1. Assert UPDATE fails with trigger SQLSTATE 45000
        $updateBlocked = false;
        try {
            DB::table('audit_logs')->where('id', $log->id)->update([
                'reason' => 'Tampered Login Reason',
            ]);
        } catch (QueryException $e) {
            $updateBlocked = true;
            $this->assertStringContainsString('45000', $e->getMessage());
        }
        $this->assertTrue($updateBlocked, 'Trigger failed to block update on audit_logs table');

        // 2. Assert DELETE fails with trigger SQLSTATE 45000
        $deleteBlocked = false;
        try {
            DB::table('audit_logs')->where('id', $log->id)->delete();
        } catch (QueryException $e) {
            $deleteBlocked = true;
            $this->assertStringContainsString('45000', $e->getMessage());
        }
        $this->assertTrue($deleteBlocked, 'Trigger failed to block delete on audit_logs table');
    }
}
