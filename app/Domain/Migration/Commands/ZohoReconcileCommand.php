<?php

declare(strict_types=1);

namespace App\Domain\Migration\Commands;

use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Services\ReconciliationReportGenerator;
use App\Domain\Migration\Services\ZohoReconciliationService;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class ZohoReconcileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zoho:reconcile 
        {--batch= : Import batch UUID} 
        {--workspace=ws-emk-dhaka : Workspace identifier} 
        {--format=console : Output format (console|csv|html)} 
        {--output= : Destination file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cross-validate legacy Zoho source records against imported CRM database records';

    public function handle(
        ZohoReconciliationService $reconciliationService,
        ReconciliationReportGenerator $reportGenerator
    ): int {
        $workspaceId = (string) ($this->option('workspace') ?: 'ws-emk-dhaka');
        $batchUuid = $this->option('batch') ? (string) $this->option('batch') : null;
        $format = strtolower((string) ($this->option('format') ?: 'console'));
        $outputPath = $this->option('output') ? (string) $this->option('output') : null;

        // 1. Assert workspace exists
        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            $this->error("Error: Workspace [{$workspaceId}] not found.");
            return self::FAILURE;
        }

        // 2. Run reconciliation across single batch or entire workspace
        if (!empty($batchUuid)) {
            $batch = ZohoImportBatch::where('batch_uuid', $batchUuid)
                ->where('workspace_id', $workspaceId)
                ->first();

            if (!$batch) {
                $this->error("Error: Import batch [{$batchUuid}] not found in workspace [{$workspaceId}].");
                return self::FAILURE;
            }

            $report = $reconciliationService->reconcileBatch($batch);
        } else {
            $report = $reconciliationService->reconcileWorkspace($workspaceId);
        }

        // 3. Render or export output based on chosen format
        return match ($format) {
            'csv' => $this->handleCsvOutput($reportGenerator->toCsv($report), $outputPath),
            'html' => $this->handleHtmlOutput($reportGenerator->toHtml($report), $outputPath),
            default => $this->handleConsoleOutput($report),
        };
    }

    protected function handleCsvOutput(string $csv, ?string $outputPath): int
    {
        if (!empty($outputPath)) {
            file_put_contents($outputPath, $csv);
            $this->info("CSV reconciliation report saved to [{$outputPath}].");
        } else {
            $this->line($csv);
        }

        return self::SUCCESS;
    }

    protected function handleHtmlOutput(string $html, ?string $outputPath): int
    {
        if (!empty($outputPath)) {
            file_put_contents($outputPath, $html);
            $this->info("HTML reconciliation report saved to [{$outputPath}].");
        } else {
            $this->line($html);
        }

        return self::SUCCESS;
    }

    protected function handleConsoleOutput(\App\Domain\Migration\DTOs\ReconciliationReportDTO $report): int
    {
        $this->info('======================================================');
        $this->info('         Zoho Migration Reconciliation Report         ');
        $this->info('======================================================');

        $statusLabel = $report->isReconciled() ? '<fg=green;options=bold>100% RECONCILED</>' : '<fg=red;options=bold>DISCREPANCIES DETECTED</>';

        $this->table(
            ['Metric', 'Value'],
            [
                ['Workspace', $report->workspaceId],
                ['Batch UUID', $report->batchUuid],
                ['Generated At', $report->generatedAt],
                ['Source Records', (string) $report->sourceRecordCount],
                ['Imported Records', (string) $report->importedRecordCount],
                ['Match Percentage', $report->matchPercentage() . '%'],
                ['Reconciliation Status', $statusLabel],
                ['Total Discrepancies', (string) $report->discrepancyCount],
                ['Date Divergences', (string) $report->dateMismatchCount],
                ['Missing Attachments', (string) $report->missingAttachments],
                ['Unique Emails (Source / Imported)', "{$report->sourceUniqueEmails} / {$report->importedUniqueEmails}"],
            ]
        );

        if (!empty($report->discrepancies)) {
            $this->newLine();
            $this->warn("Found {$report->discrepancyCount} discrepancies:");

            $rows = array_map(function ($d) {
                return [
                    $d['zoho_id'] ?? '',
                    $d['entity'] ?? '',
                    $d['field'] ?? '',
                    substr((string) ($d['expected'] ?? ''), 0, 20),
                    substr((string) ($d['actual'] ?? ''), 0, 20),
                    $d['severity'] ?? '',
                    $d['message'] ?? '',
                ];
            }, $report->discrepancies);

            $this->table(
                ['Zoho ID', 'Entity', 'Field', 'Expected', 'Actual', 'Severity', 'Message'],
                $rows
            );
        } else {
            $this->info('Zero discrepancies detected. Source and imported records match 100%.');
        }

        return self::SUCCESS;
    }
}
