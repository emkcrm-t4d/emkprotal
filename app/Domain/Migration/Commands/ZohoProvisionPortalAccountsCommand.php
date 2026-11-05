<?php

declare(strict_types=1);

namespace App\Domain\Migration\Commands;

use App\Domain\Migration\Services\PortalAccountProvisioningService;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Console\Command;
use Throwable;

class ZohoProvisionPortalAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zoho:provision-portal-accounts 
        {--workspace=ws-emk-dhaka : Target workspace identifier}
        {--batch= : Optional import batch UUID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision inactive, passwordless portal user accounts for legacy Zoho members per DEC-06';

    public function handle(PortalAccountProvisioningService $service): int
    {
        $workspaceId = (string) ($this->option('workspace') ?: 'ws-emk-dhaka');
        $batchUuid = $this->option('batch') ? (string) $this->option('batch') : null;

        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            $this->error("Error: Workspace [{$workspaceId}] not found.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('==========================================================');
        $this->info('  EMK Portal - Inactive Portal Account Provisioning Tool  ');
        $this->info('==========================================================');
        $this->line("  Workspace:  {$workspace->name} [{$workspaceId}]");
        if ($batchUuid) {
            $this->line("  Batch:      {$batchUuid}");
        }
        $this->line('  Security:   guard=member, is_active=false, password=null (DEC-06)');
        $this->newLine();

        try {
            $result = $service->provisionInactiveAccounts($workspaceId, $batchUuid);

            $this->table(
                ['Attribute', 'Result'],
                [
                    ['Workspace', $workspaceId],
                    ['Inactive Accounts Staged', (string) $result['staged_count']],
                    ['Credential Status', 'Passwordless (NULL)'],
                    ['Active Status', 'INACTIVE (Claim Required)'],
                    ['Audit Event Emitted', 'portal_account_staged_inactive (DEC-11)'],
                ]
            );

            $this->newLine();
            $this->info("✓ Successfully staged {$result['staged_count']} inactive portal accounts.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Provisioning failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
