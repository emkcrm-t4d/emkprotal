<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Audit\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupService
{
    /**
     * Create a full platform backup archiving database and private documents.
     *
     * @throws RuntimeException
     */
    public function createBackup(bool $includeDocuments = true, bool $encrypt = true): array
    {
        $lockKey = (string) config('backup.concurrency_lock_key', 'platform_backup_lock');
        $lockTimeout = (int) config('backup.timeout_seconds', 600);
        $lock = Cache::lock($lockKey, $lockTimeout);

        if (! $lock->get()) {
            throw new RuntimeException('A backup operation is currently in progress.');
        }

        $backupDir = $this->getBackupDirectory();
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = Carbon::now()->format('Y-m-d-His');
        $baseFilename = "backup-{$timestamp}";
        $rawZipFilename = "{$baseFilename}.zip";
        $finalFilename = $encrypt ? "{$baseFilename}.zip.enc" : $rawZipFilename;

        $tempSqlPath = tempnam(sys_get_temp_dir(), 'emk_sql_');
        $rawZipPath = $backupDir . DIRECTORY_SEPARATOR . $rawZipFilename;
        $finalArchivePath = $backupDir . DIRECTORY_SEPARATOR . $finalFilename;

        try {
            // 1. Dump database tables
            $this->dumpDatabase($tempSqlPath);

            // 2. Package database dump and documents into a ZIP archive
            $zip = new ZipArchive();
            $zipOpenResult = $zip->open($rawZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($zipOpenResult !== true) {
                throw new RuntimeException("Failed to initialize backup ZIP archive. Code: {$zipOpenResult}");
            }

            $zip->addFile($tempSqlPath, 'database.sql');

            $manifestData = [
                'app_name' => config('app.name', 'EMK Portal'),
                'created_at' => Carbon::now()->toIso8601String(),
                'encrypted' => $encrypt,
                'cipher' => $encrypt ? config('backup.cipher', 'AES-256-CBC') : 'none',
                'includes_documents' => $includeDocuments,
                'php_version' => PHP_VERSION,
                'db_driver' => DB::getDriverName(),
            ];
            $zip->addFromString('manifest.json', json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($includeDocuments) {
                $this->bundlePrivateDocuments($zip);
            }

            $zip->close();

            // 3. Encrypt archive if enabled (T-10-10)
            if ($encrypt) {
                $rawZipBytes = file_get_contents($rawZipPath);
                if ($rawZipBytes === false) {
                    throw new RuntimeException('Unable to read raw backup archive for encryption.');
                }

                $encrypter = $this->getEncrypter();
                $encryptedPayload = $encrypter->encryptString($rawZipBytes);

                file_put_contents($finalArchivePath, $encryptedPayload);

                if (file_exists($rawZipPath)) {
                    unlink($rawZipPath);
                }
            }

            // 4. Generate SHA-256 Checksum Manifest (T-10-12)
            $sha256Hash = hash_file('sha256', $finalArchivePath);
            if (! is_string($sha256Hash) || $sha256Hash === '') {
                throw new RuntimeException('Failed to calculate SHA-256 hash for backup archive.');
            }

            $sha256ManifestPath = "{$finalArchivePath}.sha256";
            file_put_contents($sha256ManifestPath, $sha256Hash . PHP_EOL);

            $fileSize = filesize($finalArchivePath) ?: 0;

            // 5. Prune old archives per retention policy
            $this->pruneOldBackups();

            // 6. Record immutable AuditLog
            $actor = Auth::user();
            AuditLog::create([
                'workspace_id' => null,
                'actor_id' => $actor?->id,
                'actor_guard' => Auth::getDefaultDriver() ?? 'web',
                'event_type' => AuditLog::EVENT_BACKUP_CREATED,
                'auditable_type' => self::class,
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => [
                    'filename' => $finalFilename,
                    'size' => $fileSize,
                    'sha256' => $sha256Hash,
                    'encrypted' => $encrypt,
                    'status' => 'success',
                ],
                'reason' => "Platform backup archive [{$finalFilename}] created successfully.",
                'ip_address' => request()?->ip() ?? '127.0.0.1',
                'user_agent' => request()?->userAgent() ?? 'System / Console',
                'created_at' => Carbon::now(),
            ]);

            return [
                'status' => 'success',
                'filename' => $finalFilename,
                'path' => $finalArchivePath,
                'size' => $fileSize,
                'sha256' => $sha256Hash,
                'encrypted' => $encrypt,
            ];
        } finally {
            if (file_exists($tempSqlPath)) {
                @unlink($tempSqlPath);
            }
            $lock->release();
        }
    }

    /**
     * Verify the integrity, checksum, and decryptability of a backup archive.
     */
    public function verifyArchive(string $archiveFilename): array
    {
        $backupDir = $this->getBackupDirectory();
        $cleanFilename = basename($archiveFilename);
        $archivePath = $backupDir . DIRECTORY_SEPARATOR . $cleanFilename;
        $manifestPath = "{$archivePath}.sha256";

        if (! file_exists($archivePath) || (filesize($archivePath) ?: 0) === 0) {
            return [
                'valid' => false,
                'error' => 'Archive file not found or is empty.',
            ];
        }

        if (! file_exists($manifestPath)) {
            return [
                'valid' => false,
                'error' => 'Checksum verification failed: checksum manifest file missing.',
            ];
        }

        // 1. Verify SHA-256 Checksum (T-10-12)
        $expectedHash = trim((string) file_get_contents($manifestPath));
        $actualHash = hash_file('sha256', $archivePath);

        if (! hash_equals($expectedHash, (string) $actualHash)) {
            return [
                'valid' => false,
                'error' => 'Checksum verification failed: archive may be corrupted or tampered.',
            ];
        }

        // 2. Decrypt archive if encrypted
        $isEncrypted = str_ends_with($cleanFilename, '.enc');
        $rawZipContent = '';

        if ($isEncrypted) {
            try {
                $encryptedPayload = file_get_contents($archivePath);
                if ($encryptedPayload === false) {
                    throw new RuntimeException('Cannot read archive for decryption.');
                }
                $encrypter = $this->getEncrypter();
                $rawZipContent = $encrypter->decryptString($encryptedPayload);
            } catch (DecryptException|Throwable) {
                return [
                    'valid' => false,
                    'error' => 'Decryption failed: invalid key.',
                ];
            }
        } else {
            $rawZipContent = (string) file_get_contents($archivePath);
        }

        // 3. Inspect ZIP table structure and contents
        $tempZipPath = tempnam(sys_get_temp_dir(), 'emk_chk_') . '.zip';
        file_put_contents($tempZipPath, $rawZipContent);

        $zip = new ZipArchive();
        $openResult = $zip->open($tempZipPath);
        if ($openResult !== true) {
            @unlink($tempZipPath);

            return [
                'valid' => false,
                'error' => 'Archive structure invalid: unable to open decrypted ZIP archive.',
            ];
        }

        $hasDatabaseSql = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = (string) $zip->getNameIndex($i);
            if ($entryName === 'database.sql' || str_ends_with($entryName, '.sql')) {
                $hasDatabaseSql = true;
                break;
            }
        }

        $zip->close();
        @unlink($tempZipPath);

        if (! $hasDatabaseSql) {
            return [
                'valid' => false,
                'error' => 'Archive structure invalid: database SQL dump missing.',
            ];
        }

        // 4. Record AuditLog verification
        $actor = Auth::user();
        AuditLog::create([
            'workspace_id' => null,
            'actor_id' => $actor?->id,
            'actor_guard' => Auth::getDefaultDriver() ?? 'web',
            'event_type' => AuditLog::EVENT_BACKUP_RESTORE_VERIFIED,
            'auditable_type' => self::class,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => [
                'archive' => $cleanFilename,
                'checksum_verified' => true,
                'decryption_verified' => true,
            ],
            'reason' => "Platform backup archive [{$cleanFilename}] passed integrity and decryption verification.",
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'System / Console',
            'created_at' => Carbon::now(),
        ]);

        return [
            'valid' => true,
            'archive' => $cleanFilename,
            'checksum_verified' => true,
            'decryption_verified' => true,
        ];
    }

    /**
     * List all available platform backups with metadata and hashes.
     *
     * @return list<array<string, mixed>>
     */
    public function listBackups(): array
    {
        $backupDir = $this->getBackupDirectory();
        if (! is_dir($backupDir)) {
            return [];
        }

        $entries = scandir($backupDir);
        if ($entries === false) {
            return [];
        }

        $backups = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_ends_with($entry, '.sha256')) {
                continue;
            }

            if (! str_starts_with($entry, 'backup-') || (! str_ends_with($entry, '.zip') && ! str_ends_with($entry, '.zip.enc'))) {
                continue;
            }

            $fullPath = $backupDir . DIRECTORY_SEPARATOR . $entry;
            if (! is_file($fullPath)) {
                continue;
            }

            $manifestPath = "{$fullPath}.sha256";
            $sha256 = file_exists($manifestPath) ? trim((string) file_get_contents($manifestPath)) : '';
            $mtime = filemtime($fullPath) ?: time();
            $sizeBytes = filesize($fullPath) ?: 0;

            $backups[] = [
                'filename' => $entry,
                'date' => Carbon::createFromTimestamp($mtime)->toIso8601String(),
                'formatted_date' => Carbon::createFromTimestamp($mtime)->format('Y-m-d H:i:s'),
                'timestamp' => $mtime,
                'size_bytes' => $sizeBytes,
                'size_formatted' => $this->formatBytes($sizeBytes),
                'encrypted' => str_ends_with($entry, '.enc'),
                'sha256' => $sha256,
            ];
        }

        usort($backups, fn (array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * Decrypt an encrypted archive into a target path or temporary ZIP file.
     */
    public function decryptArchive(string $archiveFilename, ?string $destinationPath = null): string
    {
        $backupDir = $this->getBackupDirectory();
        $cleanFilename = basename($archiveFilename);
        $archivePath = $backupDir . DIRECTORY_SEPARATOR . $cleanFilename;

        if (! file_exists($archivePath)) {
            throw new RuntimeException("Backup archive not found at [{$archivePath}].");
        }

        $isEncrypted = str_ends_with($cleanFilename, '.enc');
        if (! $isEncrypted) {
            if ($destinationPath !== null) {
                copy($archivePath, $destinationPath);

                return $destinationPath;
            }

            return $archivePath;
        }

        $encryptedPayload = file_get_contents($archivePath);
        if ($encryptedPayload === false) {
            throw new RuntimeException('Unable to read encrypted archive.');
        }

        $encrypter = $this->getEncrypter();
        $rawZip = $encrypter->decryptString($encryptedPayload);

        $targetPath = $destinationPath ?? (tempnam(sys_get_temp_dir(), 'emk_restore_') . '.zip');
        file_put_contents($targetPath, $rawZip);

        return $targetPath;
    }

    /**
     * Delete a backup archive and its checksum manifest.
     */
    public function deleteBackup(string $archiveFilename): bool
    {
        $backupDir = $this->getBackupDirectory();
        $cleanFilename = basename($archiveFilename);
        $archivePath = $backupDir . DIRECTORY_SEPARATOR . $cleanFilename;
        $manifestPath = "{$archivePath}.sha256";

        $deleted = false;
        if (file_exists($archivePath)) {
            $deleted = unlink($archivePath);
        }

        if (file_exists($manifestPath)) {
            unlink($manifestPath);
        }

        if ($deleted) {
            $actor = Auth::user();
            AuditLog::create([
                'workspace_id' => null,
                'actor_id' => $actor?->id,
                'actor_guard' => Auth::getDefaultDriver() ?? 'web',
                'event_type' => AuditLog::EVENT_BACKUP_DELETED,
                'auditable_type' => self::class,
                'auditable_id' => null,
                'old_values' => ['filename' => $cleanFilename],
                'new_values' => null,
                'reason' => "Platform backup archive [{$cleanFilename}] deleted.",
                'ip_address' => request()?->ip() ?? '127.0.0.1',
                'user_agent' => request()?->userAgent() ?? 'System / Console',
                'created_at' => Carbon::now(),
            ]);
        }

        return $deleted;
    }

    /**
     * Dump relational database tables to a SQL script.
     */
    protected function dumpDatabase(string $outputSqlPath): void
    {
        $tables = (array) config('backup.tables', []);
        $driver = DB::getDriverName();
        $pdo = DB::connection()->getPdo();

        $handle = fopen($outputSqlPath, 'w');
        if ($handle === false) {
            throw new RuntimeException("Unable to open temp SQL file at [{$outputSqlPath}].");
        }

        fwrite($handle, "-- EMK Portal Platform Database Dump\n");
        fwrite($handle, "-- Driver: {$driver}\n");
        fwrite($handle, '-- Generated: ' . Carbon::now()->toIso8601String() . "\n\n");

        if ($driver === 'sqlite') {
            fwrite($handle, "PRAGMA foreign_keys = OFF;\n\n");
        } else {
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
        }

        foreach ($tables as $table) {
            $table = (string) $table;
            if (! Schema::hasTable($table)) {
                continue;
            }

            fwrite($handle, "-- Table structure for table `{$table}`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

            if ($driver === 'sqlite') {
                $schemaRow = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
                if ($schemaRow && ! empty($schemaRow->sql)) {
                    fwrite($handle, (string) $schemaRow->sql . ";\n\n");
                }
            } else {
                try {
                    $createRow = DB::selectOne("SHOW CREATE TABLE `{$table}`");
                    $createSql = $createRow?->{'Create Table'} ?? null;
                    if ($createSql) {
                        fwrite($handle, (string) $createSql . ";\n\n");
                    }
                } catch (Throwable) {
                    // Fallback to table listing if create statement fails
                }
            }

            fwrite($handle, "-- Dumping data for table `{$table}`\n");
            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                if (empty($rowArray)) {
                    continue;
                }

                $columns = array_map(fn (string $c): string => "`{$c}`", array_keys($rowArray));
                $escapedValues = array_map(function ($val) use ($pdo): string {
                    if ($val === null) {
                        return 'NULL';
                    }
                    if (is_int($val) || is_float($val)) {
                        return (string) $val;
                    }
                    if (is_bool($val)) {
                        return $val ? '1' : '0';
                    }

                    return $pdo->quote((string) $val);
                }, array_values($rowArray));

                fwrite($handle, "INSERT INTO `{$table}` (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $escapedValues) . ");\n");
            }
            fwrite($handle, "\n");
        }

        if ($driver === 'sqlite') {
            fwrite($handle, "PRAGMA foreign_keys = ON;\n");
        } else {
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        }

        fclose($handle);
    }

    /**
     * Bundle all private storage documents into the open ZIP archive.
     */
    protected function bundlePrivateDocuments(ZipArchive $zip): void
    {
        $diskName = (string) config('backup.documents_disk', 'private');
        $disk = Storage::disk($diskName);

        $allFiles = $disk->allFiles();
        foreach ($allFiles as $filePath) {
            $normalizedPath = str_replace('\\', '/', $filePath);
            if (str_starts_with($normalizedPath, '.') || str_contains($normalizedPath, 'backups/')) {
                continue;
            }

            $stream = $disk->readStream($filePath);
            if ($stream !== null) {
                $contents = stream_get_contents($stream);
                if ($contents !== false) {
                    $zip->addFromString('documents/' . $normalizedPath, $contents);
                }
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    /**
     * Prune archives older than the configured retention threshold.
     */
    protected function pruneOldBackups(): void
    {
        $retentionDays = (int) config('backup.retention_days', 30);
        $thresholdTimestamp = Carbon::now()->subDays($retentionDays)->timestamp;
        $backupDir = $this->getBackupDirectory();

        if (! is_dir($backupDir)) {
            return;
        }

        $files = scandir($backupDir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || str_ends_with($file, '.sha256')) {
                continue;
            }

            if (! str_starts_with($file, 'backup-')) {
                continue;
            }

            $filePath = $backupDir . DIRECTORY_SEPARATOR . $file;
            $mtime = filemtime($filePath) ?: time();

            if ($mtime < $thresholdTimestamp) {
                $this->deleteBackup($file);
            }
        }
    }

    /**
     * Resolve the storage directory for backup archives.
     */
    protected function getBackupDirectory(): string
    {
        $dir = config('backup.path');
        if (is_string($dir) && $dir !== '') {
            return $dir;
        }

        return app()->storagePath('app/backups');
    }

    /**
     * Resolve the configured AES-256 encrypter instance.
     */
    protected function getEncrypter(): Encrypter
    {
        $key = (string) (config('backup.encryption_key') ?: config('app.key'));
        if (str_starts_with($key, 'base64:')) {
            $rawKey = base64_decode(substr($key, 7));
        } elseif (strlen($key) === 32) {
            $rawKey = $key;
        } else {
            $rawKey = hash('sha256', $key, true);
        }

        $cipher = (string) config('backup.cipher', 'AES-256-CBC');

        return new Encrypter($rawKey, $cipher);
    }

    /**
     * Format byte values to human-readable strings.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
