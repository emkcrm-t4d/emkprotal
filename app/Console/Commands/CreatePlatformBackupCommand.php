<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Platform\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class CreatePlatformBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:backup:create {--no-docs : Exclude private storage documents} {--no-encrypt : Skip AES-256 encryption}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a full encrypted backup of the database and private member documents';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $includeDocs = ! (bool) $this->option('no-docs');
        $encrypt = ! (bool) $this->option('no-encrypt');

        $this->info('Initializing EMK Platform backup...');
        $this->line(sprintf('  - Include documents: %s', $includeDocs ? 'YES' : 'NO'));
        $this->line(sprintf('  - Encryption: %s', $encrypt ? 'AES-256-CBC' : 'NONE'));

        try {
            $result = $backupService->createBackup($includeDocs, $encrypt);

            $this->newLine();
            $this->info('✓ Platform backup created successfully:');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Filename', $result['filename']],
                    ['Archive Path', $result['path']],
                    ['Size', $this->formatBytes($result['size'])],
                    ['Encrypted', $result['encrypted'] ? 'Yes (AES-256-CBC)' : 'No'],
                    ['SHA-256 Checksum', $result['sha256']],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Failed to create platform backup: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Format byte values to human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
