<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Mail\Services\WorkflowRuleEngine;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class ProcessMembershipMilestonesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:process-membership-milestones {--workspace= : Optional workspace ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan workspace members daily for 30-day renewal windows and transition expired active members to inactive';

    /**
     * Execute the console command.
     */
    public function handle(WorkflowRuleEngine $engine): int
    {
        $targetWorkspace = $this->option('workspace');

        if (is_string($targetWorkspace) && trim($targetWorkspace) !== '') {
            $workspaces = [trim($targetWorkspace)];
        } else {
            $workspaces = Workspace::where('is_active', true)->pluck('id')->map(fn ($id): string => (string) $id)->all();
            if (empty($workspaces)) {
                $workspaces = ['ws-emk-dhaka'];
            }
        }

        foreach ($workspaces as $workspaceId) {
            $summary = $engine->processWorkspaceMembershipMilestones($workspaceId);
            $this->info(sprintf(
                '[%s] Processed membership milestones: renewal_notices_queued=%d, expired_transitions=%d',
                $workspaceId,
                $summary['renewal_notices_queued'],
                $summary['expired_transitions']
            ));
        }

        return self::SUCCESS;
    }
}
