<?php

declare(strict_types=1);

namespace App\Domain\Migration\Commands;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\Exceptions\SecurityException;
use App\Domain\Migration\Services\ZohoAttachmentMigrationService;
use App\Domain\Workspaces\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ZohoImportAttachmentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zoho:import-attachments 
        {directory : Path to extracted attachments directory}
        {--manifest= : Path to CSV manifest file mapping Zoho IDs to filenames}
        {--workspace=ws-emk-dhaka : Target workspace identifier}
        {--dry-run : Validate and simulate attachment processing without storing files or creating database records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batch import legacy Zoho attachments from extracted directory using a CSV manifest';

    public function handle(ZohoAttachmentMigrationService $migrationService): int
    {
        $directory = (string) $this->argument('directory');
        $manifestPath = (string) $this->option('manifest');
        $workspaceId = (string) $this->option('workspace');
        $dryRun = (bool) $this->option('dry-run');

        // 1. Validate directory existence and readability
        if (!is_dir($directory) || !is_readable($directory)) {
            $this->error("Error: Attachments directory [{$directory}] does not exist or is not readable.");
            return self::FAILURE;
        }

        // 2. Validate manifest file
        if ($manifestPath === '') {
            $defaultManifest = rtrim($directory, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . 'manifest.csv';
            if (file_exists($defaultManifest) && is_readable($defaultManifest)) {
                $manifestPath = $defaultManifest;
            } else {
                $this->error("Error: Manifest file path must be specified via --manifest option.");
                return self::FAILURE;
            }
        }

        if (!file_exists($manifestPath) || !is_readable($manifestPath)) {
            $this->error("Error: Manifest CSV file [{$manifestPath}] does not exist or is not readable.");
            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($manifestPath, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $this->error("Error: Manifest file [{$manifestPath}] must have a .csv extension.");
            return self::FAILURE;
        }

        // 3. Validate workspace
        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            $this->error("Error: Target workspace [{$workspaceId}] does not exist.");
            return self::FAILURE;
        }

        // 4. Parse and validate manifest CSV headers
        $handle = fopen($manifestPath, 'r');
        if ($handle === false) {
            $this->error("Error: Unable to open manifest CSV file [{$manifestPath}].");
            return self::FAILURE;
        }

        $rawHeaders = fgetcsv($handle);
        if ($rawHeaders === false || empty($rawHeaders)) {
            fclose($handle);
            $this->error("Error: Manifest CSV file [{$manifestPath}] is empty or malformed.");
            return self::FAILURE;
        }

        // Remove UTF-8 BOM if present
        $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeaders[0]);

        $headerMap = [];
        foreach ($rawHeaders as $index => $rawCol) {
            $normalized = strtolower(trim(str_replace([' ', '-'], '_', (string) $rawCol)));
            if (in_array($normalized, ['zoho_id', 'zohoid', 'contactid', 'leadid'], true)) {
                $headerMap['zoho_id'] = $index;
            } elseif (in_array($normalized, ['filename', 'file_name', 'file'], true)) {
                $headerMap['filename'] = $index;
            } elseif (in_array($normalized, ['document_type', 'documenttype', 'type'], true)) {
                $headerMap['document_type'] = $index;
            }
        }

        if (!isset($headerMap['zoho_id']) || !isset($headerMap['filename']) || !isset($headerMap['document_type'])) {
            fclose($handle);
            $this->error("Error: Manifest CSV is missing required headers (zoho_id, filename, document_type). Found: " . implode(', ', $rawHeaders));
            return self::FAILURE;
        }

        // Count non-empty manifest rows
        $rows = [];
        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                continue;
            }
            $rows[] = [
                'rowNum' => $rowNum,
                'zoho_id' => trim((string) ($row[$headerMap['zoho_id']] ?? '')),
                'filename' => trim((string) ($row[$headerMap['filename']] ?? '')),
                'document_type' => trim((string) ($row[$headerMap['document_type']] ?? '')),
            ];
        }
        fclose($handle);

        $totalRows = count($rows);

        // 5. Render console banner
        $this->newLine();
        $this->info("==========================================================");
        $this->info("    EMK Portal - Legacy Zoho Attachment Import Processor   ");
        $this->info("==========================================================");
        $this->line("  Directory:   {$directory}");
        $this->line("  Manifest:    {$manifestPath}");
        $this->line("  Workspace:   {$workspace->name} [{$workspaceId}]");
        $this->line("  Total Rows:  {$totalRows}");
        $this->line("  Dry Run:     " . ($dryRun ? 'YES (Validation only, no writes)' : 'NO (Persisting to private storage & DB)'));
        $this->newLine();

        $startTime = microtime(true);
        $progressBar = $this->output->createProgressBar($totalRows);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% (%elapsed:6s% / %estimated:-6s%)');
        $progressBar->start();

        $succeeded = 0;
        $missingFiles = 0;
        $orphanZohoIds = 0;
        $skippedOrErrors = 0;
        $allowedDocTypes = ['identity_document', 'studio_photo', 'parental_consent'];

        foreach ($rows as $item) {
            $zohoId = $item['zoho_id'];
            $filename = $item['filename'];
            $docType = $item['document_type'];

            // Validate document_type enum
            if (!in_array($docType, $allowedDocTypes, true)) {
                $this->newLine();
                $this->warn("Row {$item['rowNum']}: Invalid document type [{$docType}] for record [{$zohoId}]. Skipping.");
                $skippedOrErrors++;
                $progressBar->advance();
                continue;
            }

            // Path traversal defense & resolution
            try {
                $resolvedPath = $migrationService->sanitizeRelativePath($directory, $filename);
            } catch (SecurityException $se) {
                $this->newLine();
                $this->error("Row {$item['rowNum']}: Security violation for path [{$filename}]: " . $se->getMessage());
                $skippedOrErrors++;
                $progressBar->advance();
                continue;
            }

            // Check source file existence
            if (!file_exists($resolvedPath) || !is_readable($resolvedPath)) {
                $this->newLine();
                $this->warn("Row {$item['rowNum']}: Source file [{$filename}] missing or unreadable in [{$directory}].");
                $missingFiles++;
                $progressBar->advance();
                continue;
            }

            // Check target entity existence
            $entityExists = Member::where('zoho_id', $zohoId)->exists() || Lead::where('zoho_id', $zohoId)->exists();
            if (!$entityExists) {
                $this->newLine();
                $this->warn("Row {$item['rowNum']}: Target Zoho ID [{$zohoId}] not found in members or leads (Orphan).");
                $orphanZohoIds++;
                $progressBar->advance();
                continue;
            }

            // Process attachment
            try {
                if ($dryRun) {
                    // In dry-run mode, validate file security and magic bytes without storing
                    $migrationService->validateFileSecurity($resolvedPath);
                    $succeeded++;
                } else {
                    $migrationService->migrateAttachment($resolvedPath, $zohoId, $docType, $workspaceId);
                    $succeeded++;
                }
            } catch (Throwable $e) {
                $this->newLine();
                $this->error("Row {$item['rowNum']}: Failed to process attachment [{$filename}] for Zoho ID [{$zohoId}]: " . $e->getMessage());
                $skippedOrErrors++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $executionTime = round(microtime(true) - $startTime, 2);

        $this->info("✓ Zoho attachment migration batch completed.");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Files in Manifest', (string) $totalRows],
                ['Successfully Stored', (string) $succeeded],
                ['Missing Files', (string) $missingFiles],
                ['Orphan Zoho IDs', (string) $orphanZohoIds],
                ['Skipped / Errors', (string) $skippedOrErrors],
                ['Execution Mode', $dryRun ? 'Dry Run (Validation Only)' : 'Production (Encrypted Storage & Linked)'],
                ['Execution Time', "{$executionTime}s"],
            ]
        );

        // 6. Write consolidated audit event in audit_logs per DEC-11
        AuditLog::create([
            'workspace_id' => $workspaceId,
            'actor_id' => Auth::id(),
            'actor_guard' => 'system',
            'event_type' => AuditLog::EVENT_ZOHO_ATTACHMENTS_BATCH_IMPORTED,
            'auditable_type' => null,
            'auditable_id' => null,
            'new_values' => [
                'directory' => $directory,
                'manifest' => $manifestPath,
                'total_manifest_rows' => $totalRows,
                'successful_imports' => $succeeded,
                'missing_files' => $missingFiles,
                'orphan_attachments' => $orphanZohoIds,
                'skipped_or_errors' => $skippedOrErrors,
                'dry_run' => $dryRun,
                'execution_time_seconds' => $executionTime,
            ],
            'reason' => "Imported Zoho attachments batch from [{$manifestPath}]: {$succeeded} succeeded, {$missingFiles} missing, {$orphanZohoIds} orphans, {$skippedOrErrors} errors.",
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'Artisan CLI / ZohoImportAttachmentsCommand',
            'created_at' => Carbon::now(),
        ]);

        return self::SUCCESS;
    }
}
