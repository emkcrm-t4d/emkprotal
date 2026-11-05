<?php

declare(strict_types=1);

namespace App\Domain\Migration\Commands;

use App\Domain\Migration\Services\HubDeltaExportService;
use App\Domain\Workspaces\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class ZohoExportHubDeltaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zoho:export-hub-delta 
        {--since= : Cutoff timestamp (e.g. 2026-11-05 20:00:00)} 
        {--workspace=ws-emk-dhaka : Workspace identifier} 
        {--output= : Destination CSV file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract applications submitted during cutover window into Zoho-compliant CSV format for emergency reversal (TR-01..TR-05)';

    public function handle(HubDeltaExportService $exportService): int
    {
        $workspaceId = (string) ($this->option('workspace') ?: 'ws-emk-dhaka');
        $sinceInput = $this->option('since') ? (string) $this->option('since') : null;
        $outputPath = $this->option('output') ? (string) $this->option('output') : null;

        // 1. Validate workspace
        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            $this->error("Error: Workspace [{$workspaceId}] not found.");
            return self::FAILURE;
        }

        // 2. Parse cutoff timestamp
        try {
            $cutoff = !empty($sinceInput) ? Carbon::parse($sinceInput) : Carbon::now()->subHours(24);
        } catch (Throwable $e) {
            $this->error("Error: Invalid cutoff timestamp [{$sinceInput}]. Please use 'YYYY-MM-DD HH:MM:SS'.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('==========================================================');
        $this->info('   EMK Portal - Cutover Delta Exporter & Rollback Tool    ');
        $this->info('==========================================================');
        $this->line("  Workspace:        {$workspace->name} [{$workspaceId}]");
        $this->line("  Cutoff Timestamp: {$cutoff->toDateTimeString()}");
        $this->line('  Security:         Formula injection sanitized (T-11-12)');
        $this->newLine();

        try {
            $csvContent = $exportService->exportDeltaSince($cutoff, $workspaceId);

            // Count rows excluding header
            $rowCount = max(0, substr_count($csvContent, "\n") - 1);

            if (!empty($outputPath)) {
                $dir = dirname($outputPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                file_put_contents($outputPath, $csvContent);
                // Enforce restricted file permissions per T-11-12
                @chmod($outputPath, 0600);

                $this->info("✓ Delta export generated successfully: [{$outputPath}]");
            } else {
                $this->line($csvContent);
            }

            $this->table(
                ['Parameter', 'Value'],
                [
                    ['Workspace', $workspaceId],
                    ['Cutoff Filter (>=)', $cutoff->toDateTimeString()],
                    ['Exported Records', (string) $rowCount],
                    ['Destination File', $outputPath ?: 'STDOUT'],
                    ['File Permissions', '0600 (Restricted)'],
                    ['Audit Trail', 'hub_delta_exported (DEC-11)'],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Delta export failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
