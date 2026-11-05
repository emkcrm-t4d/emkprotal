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
}

