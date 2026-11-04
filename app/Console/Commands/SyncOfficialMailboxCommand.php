<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Mail\Services\MailboxSyncService;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Console\Command;

class SyncOfficialMailboxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:mailbox-sync {--workspace= : Optional workspace ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize the official Information Desk IMAP mailbox with workspace Lead and Member email threads';

    /**
     * Execute the console command.
     */
    public function handle(MailboxSyncService $syncService): int
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
            $summary = $syncService->syncWorkspaceMailbox($workspaceId);
            $this->info(sprintf(
                '[%s] Synced mailbox: imported=%d, skipped_duplicates=%d, unmatched=%d',
                $workspaceId,
                $summary['imported'],
                $summary['skipped_duplicates'],
                $summary['unmatched']
            ));
        }

        return self::SUCCESS;
    }
}
