<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Domain\Platform\Services\BackupService;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

#[Layout('layouts.admin')]
class BackupManager extends Component
{
    public string $workspaceId = '';
    public string $workspace = '';
    public bool $isBackingUp = false;
    public ?string $verificationStatus = null;
    public ?string $verificationMessage = null;
    public ?string $verifiedArchive = null;

    /**
     * Mount component asserting non-delegable authorization and workspace tenancy.
     */
    public function mount(mixed $workspace): void
    {
        $this->workspace = $workspace instanceof Workspace
            ? (string) $workspace->id
            : (string) $workspace;

        $this->workspaceId = $this->workspace;

        $this->authorizeAccess();
    }

    /**
     * Assert authorization and tenancy access per DASH-05, GAP-P1-06, and T-10-10.
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (! $user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole')
            && $user->hasRole(['superadmin', 'global_admin']);

        // Assert staff belongs to target workspace
        if (! $isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspaceId) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Strict non-delegable permission check per DASH-05
        $canManage = $user->can('platform_services.manage_settings') || $isElevatedAdmin;

        if (! $canManage) {
            App::abort(403, 'Forbidden: platform_services.manage_settings permission required.');
        }
    }

    /**
     * Trigger an on-demand encrypted platform backup.
     */
    public function triggerBackup(BackupService $backupService): void
    {
        $this->authorizeAccess();

        $this->isBackingUp = true;

        try {
            $result = $backupService->createBackup(includeDocuments: true, encrypt: true);

            session()->flash(
                'status',
                sprintf(
                    'On-demand backup [%s] (%s) generated successfully with AES-256 encryption. SHA-256: %s',
                    $result['filename'],
                    $this->formatBytes($result['size']),
                    substr((string) $result['sha256'], 0, 16) . '...'
                )
            );
        } catch (Throwable $e) {
            session()->flash('error', 'Backup failed: ' . $e->getMessage());
        } finally {
            $this->isBackingUp = false;
        }
    }

    /**
     * Run non-destructive integrity, checksum, and decryption drill on an archive.
     */
    public function runVerification(string $filename, BackupService $backupService): void
    {
        $this->authorizeAccess();

        $cleanFilename = basename($filename);
        $this->verifiedArchive = $cleanFilename;

        try {
            $result = $backupService->verifyArchive($cleanFilename);

            if ($result['valid']) {
                $this->verificationStatus = 'success';
                $this->verificationMessage = sprintf(
                    'Archive [%s] verified successfully. SHA-256 checksum matched, AES-256 decrypted cleanly, and database dump validated.',
                    $cleanFilename
                );
            } else {
                $this->verificationStatus = 'failed';
                $this->verificationMessage = sprintf(
                    'Verification drill failed for [%s]: %s',
                    $cleanFilename,
                    $result['error'] ?? 'Corrupt archive structure or invalid decryption key.'
                );
            }
        } catch (Throwable $e) {
            $this->verificationStatus = 'failed';
            $this->verificationMessage = 'Verification drill exception: ' . $e->getMessage();
        }
    }

    /**
     * Download the encrypted backup archive.
     */
    public function downloadBackup(string $filename): BinaryFileResponse
    {
        $this->authorizeAccess();

        $cleanFilename = basename($filename);
        $backupDir = (string) (config('backup.path') ?: storage_path('app/backups'));
        $filePath = $backupDir . DIRECTORY_SEPARATOR . $cleanFilename;

        if (! file_exists($filePath)) {
            App::abort(404, 'Backup archive not found.');
        }

        return response()->download($filePath, $cleanFilename, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$cleanFilename}\"",
        ]);
    }

    /**
     * Delete an existing backup archive and its checksum manifest.
     */
    public function deleteBackup(string $filename, BackupService $backupService): void
    {
        $this->authorizeAccess();

        $cleanFilename = basename($filename);
        $backupService->deleteBackup($cleanFilename);

        session()->flash('status', "Backup archive [{$cleanFilename}] and associated checksum manifest deleted.");
    }

    /**
     * Render the Livewire component.
     */
    public function render(BackupService $backupService): View
    {
        $backups = $backupService->listBackups();

        $totalBytes = 0;
        $latestTimestamp = null;
        if (! empty($backups)) {
            $latestTimestamp = $backups[0]['formatted_date'];
            foreach ($backups as $b) {
                $totalBytes += (int) ($b['size_bytes'] ?? 0);
            }
        }

        $metrics = [
            'total_count' => count($backups),
            'latest_backup' => $latestTimestamp ?? 'None created yet',
            'storage_used' => $this->formatBytes($totalBytes),
            'cipher' => (string) config('backup.cipher', 'AES-256-CBC'),
            'retention_days' => (int) config('backup.retention_days', 30),
            'rpo_target' => (int) config('backup.rpo_target_hours', 1),
            'rto_target' => (int) config('backup.rto_target_hours', 4),
        ];

        return view('livewire.platform.backup-manager', [
            'backups' => $backups,
            'metrics' => $metrics,
        ])->layout('layouts.admin', [
            'title' => 'System Backups & Recovery Health - EMK Membership CRM',
            'activeModule' => 'platform_services',
        ]);
    }

    /**
     * Format byte values to human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int) $pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
