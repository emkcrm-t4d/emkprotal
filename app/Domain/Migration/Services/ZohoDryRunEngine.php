<?php

declare(strict_types=1);

namespace App\Domain\Migration\Services;

use App\Domain\Migration\DTOs\ReconciliationReportDTO;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ZohoDryRunEngine
{
    public function __construct(
        protected ZohoDataIngestionService $ingestionService,
        protected ZohoReconciliationService $reconciliationService,
        protected ZohoAttachmentMigrationService $attachmentMigrationService
    ) {}

    /**
     * Execute transactional dry-run simulation of legacy Zoho CSV ingestion and reconciliation.
     * Guaranteed atomic rollback in finally block leaving zero database mutations per MIGR-05 and T-11-10.
     *
     * @param  string  $filePath
     * @param  string  $workspaceId
     * @param  string  $sourceType  'contacts_members', 'leads', or 'form_submissions'
     * @param  string|null  $attachmentManifestPath
     * @param  string|null  $attachmentBaseDir
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException|RuntimeException|Throwable
     */
    public function executeDryRun(
        string $filePath,
        string $workspaceId,
        string $sourceType = 'contacts_members',
        ?string $attachmentManifestPath = null,
        ?string $attachmentBaseDir = null
    ): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("CSV file [{$filePath}] does not exist or is not readable.");
        }

        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            throw new InvalidArgumentException("Target workspace [{$workspaceId}] does not exist.");
        }

        $tablesToMonitor = [
            'users',
            'leads',
            'lead_attachments',
            'members',
            'invoices',
            'id_cards',
            'zoho_import_batches',
            'zoho_staging_records',
            'audit_logs',
        ];

        $countsBefore = [];
        foreach ($tablesToMonitor as $table) {
            $countsBefore[$table] = DB::table($table)->count();
        }

        DB::beginTransaction();

        try {
            // 1. Execute stream ingestion into staging and core domain tables inside transaction
            $batch = $this->ingestionService->ingestFile(
                filePath: $filePath,
                workspaceId: $workspaceId,
                sourceType: $sourceType,
                chunkSize: 100,
                dryRun: false // Run full transformation simulation
            );

            // 2. Mock attachment association without permanent disk writes if provided
            $attachmentMetrics = [
                'processed' => 0,
                'migrated' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];

            if ($attachmentManifestPath && $attachmentBaseDir && file_exists($attachmentManifestPath)) {
                $attachmentResult = $this->attachmentMigrationService->processManifest(
                    manifestPath: $attachmentManifestPath,
                    baseDir: $attachmentBaseDir,
                    dryRun: true // Mock without writing to disk
                );
                $attachmentMetrics = [
                    'processed' => $attachmentResult['processed'] ?? 0,
                    'migrated' => $attachmentResult['migrated'] ?? 0,
                    'skipped' => $attachmentResult['skipped'] ?? 0,
                    'failed' => $attachmentResult['failed'] ?? 0,
                ];
            }

            // 3. Run reconciliation check across the simulated batch
            /** @var ReconciliationReportDTO $reconciliationReport */
            $reconciliationReport = $this->reconciliationService->reconcileBatch($batch, logAudit: false);

            // 4. Collect validation errors from staging records
            $validationErrors = ZohoStagingRecord::where('batch_id', $batch->id)
                ->where('status', 'failed')
                ->pluck('error_message', 'id')
                ->toArray();

            $report = [
                'batch_uuid' => $batch->batch_uuid,
                'workspace_id' => $workspaceId,
                'source_type' => $sourceType,
                'source_file' => basename($filePath),
                'staged_count' => $batch->total_rows,
                'transformed_count' => $batch->successful_rows,
                'failed_count' => $batch->failed_rows,
                'skipped_count' => $batch->skipped_rows,
                'validation_errors' => $validationErrors,
                'reconciliation_status' => $reconciliationReport->isReconciled() ? 'reconciled' : 'discrepancies_detected',
                'reconciliation_match_rate' => $reconciliationReport->matchPercentage(),
                'discrepancy_count' => $reconciliationReport->discrepancyCount,
                'reconciliation_report' => $reconciliationReport,
                'attachment_simulation' => $attachmentMetrics,
                'rolled_back' => true,
                'database_mutations_persisted' => 0,
            ];

            return $report;
        } finally {
            // Guaranteed rollback per T-11-10
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            // Verify database state is completely unchanged
            $countsAfter = [];
            $stateUnchanged = true;
            foreach ($tablesToMonitor as $table) {
                $countsAfter[$table] = DB::table($table)->count();
                if ($countsAfter[$table] !== $countsBefore[$table]) {
                    $stateUnchanged = false;
                }
            }

            if (!$stateUnchanged) {
                throw new RuntimeException('Dry-run transaction rollback failed to preserve database invariants. Record counts changed.');
            }
        }
    }
}
