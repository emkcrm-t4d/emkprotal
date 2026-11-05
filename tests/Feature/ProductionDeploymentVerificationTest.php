<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Platform\Services\SystemHealthChecker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionDeploymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test crm:system:health-check command executes cleanly in test environment.
     */
    public function test_health_check_command_executes_successfully(): void
    {
        $this->artisan('crm:system:health-check')
            ->expectsOutputToContain('EMK PORTAL PRODUCTION HEALTH CHECK')
            ->assertExitCode(0);
    }

    /**
     * Test crm:system:health-check with --json flag emits valid parseable telemetry.
     */
    public function test_health_check_command_json_output(): void
    {
        $this->artisan('crm:system:health-check', ['--json' => true])
            ->assertExitCode(0);

        /** @var SystemHealthChecker $checker */
        $checker = app(SystemHealthChecker::class);
        $report = $checker->runAllChecks();

        $this->assertIsArray($report);
        $this->assertArrayHasKey('status', $report);
        $this->assertArrayHasKey('timestamp', $report);
        $this->assertArrayHasKey('environment', $report);
        $this->assertArrayHasKey('checks', $report);

        // Assert all required component keys exist per RELS-04
        $this->assertArrayHasKey('database', $report);
        $this->assertArrayHasKey('redis', $report);
        $this->assertArrayHasKey('storage', $report);
        $this->assertArrayHasKey('queues', $report);
        $this->assertArrayHasKey('app_key', $report);
        $this->assertArrayHasKey('scheduler', $report);

        $this->assertEquals('healthy', $report['database']['status']);
        $this->assertEquals('healthy', $report['storage']['status']);
        $this->assertEquals('healthy', $report['app_key']['status']);
    }

    /**
     * Test crm:system:health-check detects critical subsystem failure and exits with status 1.
     */
    public function test_health_check_command_exits_with_failure_on_critical_fault(): void
    {
        // Corrupt APP_KEY to simulate critical configuration failure
        config(['app.key' => 'invalid-too-short-key']);

        $this->artisan('crm:system:health-check')
            ->expectsOutputToContain('CRITICAL FAILURE')
            ->assertExitCode(1);

        // Also test JSON mode exit code on critical failure
        $this->artisan('crm:system:health-check', ['--json' => true])
            ->assertExitCode(1);
    }

    /**
     * Test SystemHealthChecker service methods return consistent contract shapes.
     */
    public function test_health_checker_service_direct_invocations(): void
    {
        /** @var SystemHealthChecker $checker */
        $checker = app(SystemHealthChecker::class);

        // Database
        $db = $checker->checkDatabase();
        $this->assertContains($db['status'], ['healthy', 'warning', 'critical']);
        $this->assertNotEmpty($db['message']);
        $this->assertIsArray($db['details']);

        // Storage
        $storage = $checker->checkStorage();
        $this->assertEquals('healthy', $storage['status']);
        $this->assertTrue($storage['details']['storage/app/private']['writable']);
        $this->assertTrue($storage['details']['storage/app/private/documents']['writable']);

        // App Key
        $appKey = $checker->checkAppKey();
        $this->assertEquals('healthy', $appKey['status']);
        $this->assertTrue($appKey['details']['valid']);

        // Scheduler with recorded heartbeat
        Cache::put('crm:scheduler:heartbeat', now()->toIso8601String(), 300);
        $sched = $checker->checkScheduler();
        $this->assertEquals('healthy', $sched['status']);
        $this->assertStringContainsString('Scheduler heartbeat active', $sched['message']);

        // Queues
        $queues = $checker->checkQueues();
        $this->assertContains($queues['status'], ['healthy', 'warning', 'critical']);
        $this->assertArrayHasKey('queues', $queues['details']);
    }

    /**
     * Test provisioning a new named superadmin with strong password via crm:provision:admin.
     */
    public function test_provision_admin_command_creates_new_named_superadmin(): void
    {
        $this->artisan('crm:provision:admin', [
            '--email' => 'sysadmin@emk.test',
            '--name' => 'Sys Admin',
            '--role' => 'superadmin',
            '--password' => 'SecurePassword123!',
        ])
            ->expectsOutputToContain('sysadmin@emk.test')
            ->expectsOutputToContain('superadmin')
            ->assertExitCode(0);

        $user = User::where('email', 'sysadmin@emk.test')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Sys Admin', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_reset_password);
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));
        $this->assertTrue($user->hasRole('superadmin'));

        // Verify immutable audit log per DEC-11
        $auditLog = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event_type', AuditLog::EVENT_ADMIN_PROVISIONED)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals(AuditLog::EVENT_ADMIN_PROVISIONED, $auditLog->event_type);
        $this->assertEquals('sysadmin@emk.test', $auditLog->new_values['email']);
        $this->assertEquals('superadmin', $auditLog->new_values['role']);
        $this->assertNull($auditLog->old_values);
    }

    /**
     * Test password rotation and role update on existing administrator via crm:provision:admin.
     */
    public function test_provision_admin_command_rotates_existing_admin_password(): void
    {
        $existing = User::create([
            'name' => 'Initial Admin',
            'email' => 'existing.admin@emk.test',
            'password' => Hash::make('OldPassword123!'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $existing->assignRole('superadmin');

        $this->artisan('crm:provision:admin', [
            '--email' => 'existing.admin@emk.test',
            '--name' => 'Updated Admin Name',
            '--role' => 'admin',
            '--password' => 'RotatedNewPass999#',
        ])
            ->expectsOutputToContain('existing.admin@emk.test')
            ->assertExitCode(0);

        $existing->refresh();
        $this->assertEquals('Updated Admin Name', $existing->name);
        $this->assertTrue(Hash::check('RotatedNewPass999#', $existing->password));
        $this->assertTrue($existing->hasRole('global_admin'));

        // Verify password_rotated audit log
        $rotationLog = AuditLog::where('auditable_type', User::class)
            ->where('auditable_id', $existing->id)
            ->where('event_type', AuditLog::EVENT_PASSWORD_ROTATED)
            ->first();

        $this->assertNotNull($rotationLog);
        $this->assertEquals('password_rotated', $rotationLog->new_values['action']);
    }

    /**
     * Test password complexity enforcement rejects weak credentials.
     */
    public function test_provision_admin_command_rejects_weak_passwords(): void
    {
        // 1. Too short (< 12 chars)
        $this->artisan('crm:provision:admin', [
            '--email' => 'dev@emk.test',
            '--name' => 'Dev Admin',
            '--password' => 'Short1!',
        ])
            ->expectsOutputToContain('at least 12 characters')
            ->assertExitCode(1);

        // 2. Missing uppercase
        $this->artisan('crm:provision:admin', [
            '--email' => 'dev@emk.test',
            '--name' => 'Dev Admin',
            '--password' => 'lowercaseonly123!',
        ])
            ->expectsOutputToContain('uppercase letter')
            ->assertExitCode(1);

        // 3. Missing lowercase
        $this->artisan('crm:provision:admin', [
            '--email' => 'dev@emk.test',
            '--name' => 'Dev Admin',
            '--password' => 'UPPERCASEONLY123!',
        ])
            ->expectsOutputToContain('lowercase letter')
            ->assertExitCode(1);

        // 4. Missing number
        $this->artisan('crm:provision:admin', [
            '--email' => 'dev@emk.test',
            '--name' => 'Dev Admin',
            '--password' => 'NoNumbersHereAtAll!',
        ])
            ->expectsOutputToContain('number (0-9)')
            ->assertExitCode(1);

        // 5. Missing special character
        $this->artisan('crm:provision:admin', [
            '--email' => 'dev@emk.test',
            '--name' => 'Dev Admin',
            '--password' => 'NoSymbolsAllowed12345',
        ])
            ->expectsOutputToContain('special character/symbol')
            ->assertExitCode(1);
    }

    /**
     * Test email validation rejects invalid formats and disposable email domains.
     */
    public function test_provision_admin_command_rejects_invalid_and_disposable_emails(): void
    {
        // Invalid syntax
        $this->artisan('crm:provision:admin', [
            '--email' => 'invalid-syntax-email',
            '--name' => 'Dev Admin',
            '--password' => 'ValidPassword123!',
        ])
            ->expectsOutputToContain('valid email address is required')
            ->assertExitCode(1);

        // Disposable domain
        $this->artisan('crm:provision:admin', [
            '--email' => 'badactor@mailinator.com',
            '--name' => 'Hacker Admin',
            '--password' => 'ValidPassword123!',
        ])
            ->expectsOutputToContain('Disposable email domain [mailinator.com] is prohibited')
            ->assertExitCode(1);
    }
}


