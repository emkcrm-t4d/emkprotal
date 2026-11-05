<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Platform\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class VerifyPlatformBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:backup:verify {archive : The backup archive filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the integrity, checksum, and decryptability of a backup archive';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $archive = (string) $this->argument('archive');

        $this->info("Verifying platform backup archive: [{$archive}]...");

        try {
            $verification = $backupService->verifyArchive($archive);

            if (! $verification['valid']) {
                $this->newLine();
                $this->error('FAIL: Archive verification failed.');
                $this->line('Reason: ' . ($verification['error'] ?? 'Unknown verification error.'));

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('PASS: Backup archive integrity verified successfully.');
            $this->table(
                ['Check', 'Status'],
                [
                    ['Archive Existence & Size', 'PASS'],
                    ['SHA-256 Checksum Match', 'PASS'],
                    ['AES-256 Decryption', $verification['decryption_verified'] ? 'PASS' : 'SKIPPED (Unencrypted)'],
                    ['ZIP Structure & Database SQL', 'PASS'],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('An unexpected error occurred during verification: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
