<?php

declare(strict_types=1);

namespace App\Livewire\Migration;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Migration\DTOs\ReconciliationReportDTO;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Services\ReconciliationReportGenerator;
use App\Domain\Migration\Services\ZohoReconciliationService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class ReconciliationDashboard extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    public string $workspace = '';

    public string $workspaceId = '';

    #[Url(as: 'batch', except: '')]
    public ?string $batchId = null;

    #[Url(as: 'filter', except: 'all')]
    public string $activeFilter = 'all';

    #[Url(as: 'search', except: '')]
    public string $searchZohoId = '';

    public int $perPage = 15;

    /**
     * Mount component asserting authentication, tenancy isolation, and Spatie permission.
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
     * Defense-in-depth authorization gate per T-11-07 and DEC-01.
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole')
            && $user->hasRole(['superadmin', 'global_admin']);

        // Assert staff belongs strictly to the route workspace
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspaceId) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Assert migration.reconcile_data permission
        if (!$isElevatedAdmin && !$user->can('migration.reconcile_data')) {
            App::abort(403, 'Forbidden: migration.reconcile_data permission required.');
        }
    }

    public function updatedActiveFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearchZohoId(): void
    {
        $this->resetPage();
    }

    public function updatedBatchId(): void
    {
        $this->resetPage();
    }

    /**
     * Resolve the active reconciliation report for the selected batch or entire workspace.
     */
    protected function getReport(ZohoReconciliationService $service, bool $logAudit = false): ReconciliationReportDTO
    {
        if (!empty($this->batchId) && $this->batchId !== 'all') {
            $batch = ZohoImportBatch::where('workspace_id', $this->workspaceId)
                ->where(function ($q) {
                    $q->where('id', $this->batchId)
                        ->orWhere('batch_uuid', $this->batchId);
                })->first();

            if ($batch) {
                return $service->reconcileBatch($batch, $logAudit);
            }
        }

        return $service->reconcileWorkspace($this->workspaceId, $logAudit);
    }

    /**
     * Stream CSV export and record immutable audit log per DEC-11.
     */
    public function exportCsv(ReconciliationReportGenerator $generator, ZohoReconciliationService $service)
    {
        $this->authorizeAccess();

        $report = $this->getReport($service, false);
        $csv = $generator->toCsv($report);

        AuditLog::create([
            'workspace_id' => $this->workspaceId,
            'actor_id' => Auth::id(),
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_ZOHO_RECONCILIATION_EXPORTED,
            'auditable_type' => Workspace::class,
            'auditable_id' => null,
            'new_values' => [
                'workspace_id' => $this->workspaceId,
                'batch_id' => $this->batchId,
                'format' => 'csv',
                'discrepancies_count' => $report->discrepancyCount,
            ],
            'reason' => "Exported reconciliation CSV report for workspace [{$this->workspaceId}].",
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'Web Dashboard',
        ]);

        $filename = "reconciliation_report_{$this->workspaceId}_" . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Stream HTML export and record immutable audit log per DEC-11.
     */
    public function exportHtml(ReconciliationReportGenerator $generator, ZohoReconciliationService $service)
    {
        $this->authorizeAccess();

        $report = $this->getReport($service, false);
        $html = $generator->toHtml($report);

        AuditLog::create([
            'workspace_id' => $this->workspaceId,
            'actor_id' => Auth::id(),
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_ZOHO_RECONCILIATION_EXPORTED,
            'auditable_type' => Workspace::class,
            'auditable_id' => null,
            'new_values' => [
                'workspace_id' => $this->workspaceId,
                'batch_id' => $this->batchId,
                'format' => 'html',
                'discrepancies_count' => $report->discrepancyCount,
            ],
            'reason' => "Exported reconciliation HTML report for workspace [{$this->workspaceId}].",
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'Web Dashboard',
        ]);

        $filename = "reconciliation_report_{$this->workspaceId}_" . now()->format('Ymd_His') . '.html';

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, $filename, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function render(ZohoReconciliationService $service): View
    {
        $batches = ZohoImportBatch::where('workspace_id', $this->workspaceId)
            ->orderByDesc('id')
            ->get();

        $report = $this->getReport($service, false);

        // Apply in-memory filtering over discrepancies collection
        $filtered = collect($report->discrepancies)->filter(function (array $d) {
            if ($this->activeFilter !== 'all' && ($d['severity'] ?? '') !== $this->activeFilter) {
                return false;
            }

            if (!empty($this->searchZohoId)) {
                $needle = strtolower($this->searchZohoId);
                $zohoId = strtolower((string) ($d['zoho_id'] ?? ''));
                $message = strtolower((string) ($d['message'] ?? ''));

                return str_contains($zohoId, $needle) || str_contains($message, $needle);
            }

            return true;
        })->values();

        // Paginate filtered discrepancies
        $currentPage = $this->getPage();
        $offset = ($currentPage - 1) * $this->perPage;
        $itemsForCurrentPage = $filtered->slice($offset, $this->perPage)->values();

        $paginated = new LengthAwarePaginator(
            $itemsForCurrentPage,
            $filtered->count(),
            $this->perPage,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return view('livewire.migration.reconciliation-dashboard', [
            'report' => $report,
            'discrepancies' => $paginated,
            'batches' => $batches,
        ]);
    }
}

