<?php

declare(strict_types=1);

namespace App\Domain\Migration\Commands;

use App\Domain\Migration\Services\ZohoDataIngestionService;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Console\Command;
use Throwable;

class ZohoImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zoho:import 
        {file : Path to the Zoho export CSV file}
        {--workspace=ws-emk-dhaka : Target workspace identifier}
        {--type=contacts_members : Source data type (contacts_members|leads|form_submissions)}
        {--chunk=100 : Processing chunk size}
        {--dry-run : Validate and stage without persisting to core domain tables}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stream-chunked ingestion of legacy Zoho CSV exports into staging and core domain tables';

    public function handle(ZohoDataIngestionService $ingestionService): int
    {
        $filePath = (string) $this->argument('file');
        $workspaceId = (string) $this->option('workspace');
        $type = (string) $this->option('type');
        $chunkSize = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        // 1. Validate file
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->error("Error: CSV file [{$filePath}] does not exist or is not readable.");
            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $this->error("Error: File [{$filePath}] must have a .csv extension.");
            return self::FAILURE;
        }

        // 2. Validate workspace per T-11-03
        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            $this->error("Error: Target workspace [{$workspaceId}] does not exist.");
            return self::FAILURE;
        }

        // 3. Validate type
        $validTypes = ['contacts_members', 'leads', 'form_submissions'];
        if (!in_array($type, $validTypes, true)) {
            $this->error("Error: Invalid source type [{$type}]. Allowed values: " . implode(', ', $validTypes));
            return self::FAILURE;
        }

        if ($chunkSize <= 0) {
            $chunkSize = 100;
        }

        $fileSize = filesize($filePath) ?: 0;
        $totalRows = $ingestionService->countCsvRows($filePath);

        $this->newLine();
        $this->info("==========================================================");
        $this->info("        EMK Portal - Legacy Zoho Data Ingestion Tool      ");
        $this->info("==========================================================");
        $this->line("  File:       {$filePath} (" . $this->formatBytes($fileSize) . ")");
        $this->line("  Workspace:  {$workspace->name} [{$workspaceId}]");
        $this->line("  Type:       {$type}");
        $this->line("  Total Rows: {$totalRows}");
        $this->line("  Chunk Size: {$chunkSize}");
        $this->line("  Dry Run:    " . ($dryRun ? 'YES (Staging only, no core persistence)' : 'NO (Persisting to domain)'));
        $this->newLine();

        $startTime = microtime(true);
        $progressBar = $this->output->createProgressBar($totalRows);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% (%elapsed:6s% / %estimated:-6s%)');
        $progressBar->start();

        $lastProcessed = 0;
        $progressCallback = function (int $processed, int $total, $batch) use ($progressBar, &$lastProcessed): void {
            $advance = $processed - $lastProcessed;
            if ($advance > 0) {
                $progressBar->advance($advance);
                $lastProcessed = $processed;
            }
        };

        try {
            $batch = $ingestionService->ingestFile(
                filePath: $filePath,
                workspaceId: $workspaceId,
                sourceType: $type,
                chunkSize: $chunkSize,
                dryRun: $dryRun,
                progressCallback: $progressCallback
            );

            $progressBar->finish();
            $this->newLine(2);

            $executionTime = round(microtime(true) - $startTime, 2);

            $this->info("✓ Ingestion batch completed successfully.");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Batch UUID', $batch->batch_uuid],
                    ['Target Workspace', $workspaceId],
                    ['Source Type', $type],
                    ['Execution Mode', $dryRun ? 'Dry Run (Staging Only)' : 'Production (Staged & Imported)'],
                    ['Total Rows', (string) $batch->total_rows],
                    ['Processed Rows', (string) $batch->processed_rows],
                    ['Succeeded Rows', (string) $batch->successful_rows],
                    ['Skipped Rows', (string) $batch->skipped_rows],
                    ['Failed Rows', (string) $batch->failed_rows],
                    ['Batch Status', strtoupper((string) $batch->status)],
                    ['Execution Time', "{$executionTime}s"],
                    ['Memory Peak', $this->formatBytes(memory_get_peak_usage(true))],
                ]
            );

            return $batch->status === 'failed' ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $progressBar->finish();
            $this->newLine(2);
            $this->error("Fatal Ingestion Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2) . ' ' . ($units[$power] ?? 'B');
    }
}
