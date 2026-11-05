<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Platform\Services\SystemHealthChecker;
use Illuminate\Console\Command;

class SystemHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:system:health-check {--json : Output report in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run comprehensive production environment and worker health diagnostics.';

    /**
     * Execute the console command.
     */
    public function handle(SystemHealthChecker $healthChecker): int
    {
        $report = $healthChecker->runAllChecks();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['status'] === 'critical' ? self::FAILURE : self::SUCCESS;
        }

        $this->info('===============================================================');
        $this->info('             EMK PORTAL PRODUCTION HEALTH CHECK                ');
        $this->info('===============================================================');
        $this->line(sprintf('Environment: <comment>%s</comment> | Timestamp: <comment>%s</comment>', $report['environment'], $report['timestamp']));
        $this->newLine();

        $rows = [];
        foreach ($report['checks'] as $component => $check) {
            $statusBadge = match ($check['status']) {
                'healthy' => '<info>[HEALTHY]</info>',
                'warning' => '<comment>[WARNING]</comment>',
                'critical' => '<error>[CRITICAL]</error>',
                default => "[{$check['status']}]",
            };

            $rows[] = [
                ucfirst(str_replace('_', ' ', $component)),
                $statusBadge,
                $check['message'],
            ];
        }

        $this->table(['Subsystem', 'Status', 'Diagnostic Message'], $rows);
        $this->newLine();

        if ($report['status'] === 'healthy') {
            $this->info('✓ All production subsystems passed diagnostic checks. System is HEALTHY.');

            return self::SUCCESS;
        }

        if ($report['status'] === 'warning') {
            $this->warn('⚠ System is operational with WARNINGS. Review diagnostics above.');

            return self::SUCCESS;
        }

        $this->error('✗ CRITICAL FAILURE: One or more critical subsystems failed diagnostic check.');

        return self::FAILURE;
    }
}
