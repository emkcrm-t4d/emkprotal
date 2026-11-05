<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Platform\Services\BackupService;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BackupEngineTest extends TestCase
{
    use RefreshDatabase;

    protected BackupService $service;
    protected string $testBackupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->testBackupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'emk_test_backups_' . uniqid();
        if (! is_dir($this->testBackupDir)) {
            mkdir($this->testBackupDir, 0755, true);
        }

        config(['backup.path' => $this->testBackupDir]);
        config(['backup.retention_days' => 30]);

        // Place a dummy test file in private disk to test document bundling
        Storage::disk('private')->put('test-doc.txt', 'Secret applicant personal identity document');

        $this->service = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testBackupDir)) {
            File::deleteDirectory($this->testBackupDir);
        }

        if (Storage::disk('private')->exists('test-doc.txt')) {
            Storage::disk('private')->delete('test-doc.txt');
        }

        parent::tearDown();
    }

    public function test_artisan_command_generates_encrypted_backup_and_sha256_manifest(): void
    {
        $this->artisan('crm:backup:create')
            ->expectsOutputToContain('Initializing EMK Platform backup...')
            ->expectsOutputToContain('Platform backup created successfully')
            ->assertSuccessful();

        $files = scandir($this->testBackupDir);
        $encArchives = array_filter($files, fn ($f) => str_starts_with($f, 'backup-') && str_ends_with($f, '.zip.enc'));
        $manifests = array_filter($files, fn ($f) => str_starts_with($f, 'backup-') && str_ends_with($f, '.zip.enc.sha256'));

        $this->assertCount(1, $encArchives);
        $this->assertCount(1, $manifests);

        $archiveName = reset($encArchives);
        $archivePath = $this->testBackupDir . DIRECTORY_SEPARATOR . $archiveName;
        $manifestPath = $archivePath . '.sha256';

        $expectedHash = trim(file_get_contents($manifestPath));
        $actualHash = hash_file('sha256', $archivePath);

        $this->assertSame($expectedHash, $actualHash);
        $this->assertGreaterThan(0, filesize($archivePath));

        // Verify AuditLog entry recorded
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditLog::EVENT_BACKUP_CREATED,
        ]);
    }

    public function test_encrypted_archive_cannot_be_opened_as_raw_zip_without_decryption(): void
    {
        $result = $this->service->createBackup(includeDocuments: true, encrypt: true);
        $archivePath = $result['path'];

        $zip = new ZipArchive();
        $openResult = $zip->open($archivePath);

        // Opening encrypted raw bytes as a ZIP should fail with ER_NOZIP (not a zip archive)
        $this->assertNotTrue($openResult);

        // Decrypting via BackupService should return a valid ZIP containing database.sql
        $decryptedPath = $this->service->decryptArchive($result['filename']);
        $decryptedZip = new ZipArchive();
        $this->assertTrue($decryptedZip->open($decryptedPath));
        $this->assertNotFalse($decryptedZip->locateName('database.sql'));
        $this->assertNotFalse($decryptedZip->locateName('manifest.json'));
        $this->assertNotFalse($decryptedZip->locateName('documents/test-doc.txt'));
        $decryptedZip->close();

        @unlink($decryptedPath);
    }

    public function test_artisan_verify_confirms_valid_backup_archive_integrity(): void
    {
        $result = $this->service->createBackup(includeDocuments: true, encrypt: true);

        $this->artisan('crm:backup:verify', ['archive' => $result['filename']])
            ->expectsOutputToContain('PASS: Backup archive integrity verified successfully.')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditLog::EVENT_BACKUP_RESTORE_VERIFIED,
        ]);
    }

    public function test_corrupting_archive_byte_causes_verification_to_fail_with_checksum_error(): void
    {
        $result = $this->service->createBackup(includeDocuments: true, encrypt: true);
        $archivePath = $result['path'];

        // Tamper with archive by modifying a single byte
        $content = file_get_contents($archivePath);
        $content[10] = chr(ord($content[10]) ^ 0xFF);
        file_put_contents($archivePath, $content);

        // Verification must detect checksum failure cleanly
        $verification = $this->service->verifyArchive($result['filename']);
        $this->assertFalse($verification['valid']);
        $this->assertStringContainsString('Checksum verification failed', $verification['error']);

        // Verify CLI outputs FAIL
        $this->artisan('crm:backup:verify', ['archive' => $result['filename']])
            ->expectsOutputToContain('FAIL: Archive verification failed.')
            ->expectsOutputToContain('Checksum verification failed')
            ->assertFailed();
    }

    public function test_missing_manifest_fails_verification_cleanly(): void
    {
        $result = $this->service->createBackup(includeDocuments: true, encrypt: true);
        $manifestPath = $result['path'] . '.sha256';

        unlink($manifestPath);

        $verification = $this->service->verifyArchive($result['filename']);
        $this->assertFalse($verification['valid']);
        $this->assertStringContainsString('checksum manifest file missing', $verification['error']);
    }

    public function test_tampered_key_or_corrupt_payload_fails_decryption_check(): void
    {
        $result = $this->service->createBackup(includeDocuments: true, encrypt: true);
        $archivePath = $result['path'];
        $manifestPath = $archivePath . '.sha256';

        // Tamper content AND update checksum manifest so checksum matches but decryption fails
        $corruptPayload = '{"iv":"invalid","value":"corrupted","mac":"invalid"}';
        file_put_contents($archivePath, $corruptPayload);
        file_put_contents($manifestPath, hash_file('sha256', $archivePath) . PHP_EOL);

        $verification = $this->service->verifyArchive($result['filename']);
        $this->assertFalse($verification['valid']);
        $this->assertStringContainsString('Decryption failed', $verification['error']);
    }

    public function test_concurrency_lock_prevents_overlapping_backup_execution(): void
    {
        $lockKey = (string) config('backup.concurrency_lock_key', 'platform_backup_lock');

        // Simulate an active backup execution holding the atomic lock
        $lock = Cache::lock($lockKey, 600);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('A backup operation is currently in progress.');

            $this->service->createBackup();
        } finally {
            $lock->release();
        }
    }

    public function test_unencrypted_backup_can_be_created_and_verified(): void
    {
        $result = $this->service->createBackup(includeDocuments: false, encrypt: false);

        $this->assertStringEndsWith('.zip', $result['filename']);
        $this->assertFalse($result['encrypted']);

        // Verify unencrypted archive passes verification
        $verification = $this->service->verifyArchive($result['filename']);
        $this->assertTrue($verification['valid']);
        $this->assertSame($result['filename'], $verification['archive']);

        // Directly openable with ZipArchive
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($result['path']));
        $this->assertNotFalse($zip->locateName('database.sql'));
        $zip->close();
    }

    public function test_list_backups_returns_sorted_metadata_with_sha256(): void
    {
        $first = $this->service->createBackup(includeDocuments: false, encrypt: true);
        sleep(1);
        $second = $this->service->createBackup(includeDocuments: false, encrypt: true);

        $list = $this->service->listBackups();

        $this->assertCount(2, $list);
        $this->assertSame($second['filename'], $list[0]['filename']);
        $this->assertSame($first['filename'], $list[1]['filename']);
        $this->assertNotEmpty($list[0]['sha256']);
        $this->assertTrue($list[0]['encrypted']);
    }

    public function test_delete_backup_removes_archive_and_manifest_and_logs_audit(): void
    {
        $result = $this->service->createBackup(includeDocuments: false, encrypt: true);
        $this->assertFileExists($result['path']);
        $this->assertFileExists($result['path'] . '.sha256');

        $deleted = $this->service->deleteBackup($result['filename']);
        $this->assertTrue($deleted);

        $this->assertFileDoesNotExist($result['path']);
        $this->assertFileDoesNotExist($result['path'] . '.sha256');

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditLog::EVENT_BACKUP_DELETED,
        ]);
    }
}
