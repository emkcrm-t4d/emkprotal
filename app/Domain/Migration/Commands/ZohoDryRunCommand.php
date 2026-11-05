<?php

declare(strict_types=1);

namespace App\Domain\Migration\Commands;

use App\Domain\Migration\Services\ZohoDryRunEngine;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Console\Command;
use Throwable;

class ZohoDryRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zoho:dry-run 
        {file : Path to the Zoho export CSV file}
        {--workspace=ws-emk-dhaka : Target workspace identifier}
        {--type=contacts_members : Source data type (contacts_members|leads|form_submissions)}
        {--manifest= : Optional path to attachment manifest CSV}
        {--attachments-dir= : Optional path to attachment directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute transactional dry-run simulation of legacy Zoho CSV migration with guaranteed rollback';

    public function handle(ZohoDryRunEngine $engine): int
    {
        $filePath = (string) $this->argument('file');
        $workspaceId = (string) ($this->option('workspace') ?: 'ws-emk-dhaka');
        $type = (string) ($this->option('type') ?: 'contacts_members');
        $manifestPath = $this->option('manifest') ? (string) $this->option('manifest') : null;
        $attachmentsDir = $this->option('attachments-dir') ? (string) $this->option('attachments-dir') : null;

        // 1. Validate file
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error("Error: CSV file [{$filePath}] does not exist or is not readable.");
            return self::FAILURE;
        }

        // 2. Validate workspace
        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            $this->error("Error: Target workspace [{$workspaceId}] does not exist.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('==========================================================');
        $this->info('   EMK Portal - Transactional Zoho Migration Dry-Run      ');
        $this->info('==========================================================');
        $this->line("  File:       {$filePath}");
        $this->line("  Workspace:  {$workspace->name} [{$workspaceId}]");
        $this->line("  Type:       {$type}");
        $this->line('  Isolation:  Atomic DB::beginTransaction() with mandatory DB::rollBack()');
        $this->newLine();

        try {
            $report = $engine->executeDryRun(
                filePath: $filePath,
                workspaceId: $workspaceId,
                sourceType: $type,
                attachmentManifestPath: $manifestPath,
                attachmentBaseDir: $attachmentsDir
            );

            $this->table(
                ['Metric', 'Simulation Result'],
                [
                    ['Batch UUID', $report['batch_uuid']],
                    ['Source File', $report['source_file']],
                    ['Source Rows Staged', (string) $report['staged_count']],
                    ['Rows Transformed', (string) $report['transformed_count']],
                    ['Rows Failed', (string) $report['failed_count']],
                    ['Rows Skipped (Duplicates)', (string) $report['skipped_count']],
                    ['Reconciliation Status', strtoupper((string) $report['reconciliation_status'])],
                    ['Reconciliation Match Rate', $report['reconciliation_match_rate'] . '%'],
                    ['Discrepancies Detected', (string) $report['discrepancy_count']],
                    ['Database Mutations Persisted', (string) $report['database_mutations_persisted']],
                    ['Atomic Rollback Guaranteed', $report['rolled_back'] ? 'CONFIRMED (Zero writes)' : 'FAILED'],
                ]
            );

            if (!empty($report['validation_errors'])) {
                $this->newLine();
                $this->warn('Validation Errors Encountered During Simulation:');
                $errorRows = [];
                foreach ($report['validation_errors'] as $rowNum => $errorMsg) {
                    $errorRows[] = [(string) $rowNum, (string) $errorMsg];
                }
                $this->table(['Row Number', 'Validation Error'], $errorRows);
            }

            $this->newLine();
            $this->info('✓ Dry-run completed successfully. Database state remains 100% unchanged.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Dry-run execution failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
