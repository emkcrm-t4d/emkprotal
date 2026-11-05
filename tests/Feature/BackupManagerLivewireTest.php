<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Platform\Services\BackupService;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Platform\BackupManager;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class BackupManagerLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Workspace $otherWorkspace;
    protected User $manager;
    protected User $unauthorizedStaff;
    protected string $testBackupDir;
    protected BackupService $backupService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->testBackupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'emk_livewire_backups_' . uniqid();
        if (! is_dir($this->testBackupDir)) {
            mkdir($this->testBackupDir, 0755, true);
        }

        config(['backup.path' => $this->testBackupDir]);
        config(['backup.retention_days' => 30]);

        $this->workspace = Workspace::firstOrCreate(
            ['id' => 'ws-emk-dhaka'],
            [
                'space_id' => 1,
                'name' => 'EMK Center Dhaka',
                'is_active' => true,
            ]
        );

        $this->otherWorkspace = Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => 2,
                'name' => 'American Corner Chattogram',
                'is_active' => true,
            ]
        );

        // 1. Workspace Manager (has platform_services.manage_settings)
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Platform Settings Manager',
            'email' => 'manager.backups@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Delegated Staff (lacks platform_services.manage_settings)
        $this->unauthorizedStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Front Desk Staff',
            'email' => 'staff.unauthorized@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->unauthorizedStaff->assignRole('delegated_staff');

        $this->backupService = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testBackupDir)) {
            File::deleteDirectory($this->testBackupDir);
        }

        parent::tearDown();
    }

    public function test_workspace_manager_can_access_backups_console(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('crm.backups.index', $this->workspace->id));

        $response->assertStatus(200);
        $response->assertSee('System Backups & Recovery Health');
        $response->assertSee('ws-emk-dhaka');
        $response->assertSee('Create On-Demand Backup');
        $response->assertSee('Disaster Recovery & Non-Destructive Restore Drill', false);

        Livewire::test(BackupManager::class, ['workspace' => $this->workspace->id])
            ->assertStatus(200)
            ->assertSee('Create On-Demand Backup')
            ->assertSee('Total Archives');
    }

    public function test_unauthorized_delegated_staff_receives_http_403_forbidden(): void
    {
        $this->actingAs($this->unauthorizedStaff);

        // Route is protected by middleware
        $response = $this->get(route('crm.backups.index', $this->workspace->id));
        $response->assertStatus(403);

        // Livewire direct mount is protected by component authorization
        Livewire::test(BackupManager::class, ['workspace' => $this->workspace->id])
            ->assertStatus(403);
    }

    public function test_cross_workspace_request_is_blocked_with_http_403(): void
    {
        $this->actingAs($this->manager); // manager belongs to ws-emk-dhaka

        // Attempting to access ws-chattogram backups console
        $response = $this->get(route('crm.backups.index', $this->otherWorkspace->id));
        $response->assertStatus(403);

        Livewire::test(BackupManager::class, ['workspace' => $this->otherWorkspace->id])
            ->assertStatus(403);
    }

    public function test_trigger_backup_via_livewire_creates_archive_and_updates_component(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(BackupManager::class, ['workspace' => $this->workspace->id])
            ->call('triggerBackup')
            ->assertSee('On-demand backup')
            ->assertSee('generated successfully')
            ->assertSet('isBackingUp', false);

        $files = scandir($this->testBackupDir);
        $encArchives = array_filter($files, fn ($f) => str_starts_with($f, 'backup-') && str_ends_with($f, '.zip.enc'));

        $this->assertCount(1, $encArchives);
    }

    public function test_run_verification_on_valid_archive_updates_verification_status_to_success(): void
    {
        $result = $this->backupService->createBackup(includeDocuments: false, encrypt: true);

        $this->actingAs($this->manager);

        Livewire::test(BackupManager::class, ['workspace' => $this->workspace->id])
            ->call('runVerification', $result['filename'])
            ->assertSet('verificationStatus', 'success')
            ->assertSee('Recovery Integrity Drill: PASS')
            ->assertSee($result['filename']);
    }

    public function test_run_verification_on_corrupt_archive_updates_verification_status_to_failed(): void
    {
        $result = $this->backupService->createBackup(includeDocuments: false, encrypt: true);
        $archivePath = $result['path'];

        // Corrupt one byte
        $content = file_get_contents($archivePath);
        $content[15] = chr(ord($content[15]) ^ 0xAA);
        file_put_contents($archivePath, $content);

        $this->actingAs($this->manager);

        Livewire::test(BackupManager::class, ['workspace' => $this->workspace->id])
            ->call('runVerification', $result['filename'])
            ->assertSet('verificationStatus', 'failed')
            ->assertSee('Recovery Integrity Drill: FAILED');
    }

    public function test_manager_can_download_encrypted_backup_archive(): void
    {
        $result = $this->backupService->createBackup(includeDocuments: false, encrypt: true);

        $this->actingAs($this->manager);

        $response = $this->get(route('crm.backups.download', [
            'workspace' => $this->workspace->id,
            'filename' => $result['filename'],
        ]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/octet-stream');
        $response->assertHeader('content-disposition', "attachment; filename={$result['filename']}");
    }

    public function test_unauthorized_staff_cannot_download_backup_archive(): void
    {
        $result = $this->backupService->createBackup(includeDocuments: false, encrypt: true);

        $this->actingAs($this->unauthorizedStaff);

        $response = $this->get(route('crm.backups.download', [
            'workspace' => $this->workspace->id,
            'filename' => $result['filename'],
        ]));

        $response->assertStatus(403);
    }

    public function test_delete_backup_via_livewire_removes_archive(): void
    {
        $result = $this->backupService->createBackup(includeDocuments: false, encrypt: true);
        $this->assertFileExists($result['path']);

        $this->actingAs($this->manager);

        Livewire::test(BackupManager::class, ['workspace' => $this->workspace->id])
            ->call('deleteBackup', $result['filename'])
            ->assertSee('deleted');

        $this->assertFileDoesNotExist($result['path']);
    }
}
