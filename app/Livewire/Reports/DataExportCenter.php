<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Domain\Workspaces\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
class DataExportCenter extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';

    public string $workspaceId = '';

    #[Url(as: 'entity', except: 'members')]
    public string $entity = 'members';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'from', except: '')]
    public ?string $dateFrom = null;

    #[Url(as: 'to', except: '')]
    public ?string $dateTo = null;

    public string $datePreset = 'all';

    public string $format = 'csv';

    public string $search = '';

    /**
     * Mount component asserting authentication and workspace scoping.
     */
    public function mount(mixed $workspace): void
    {
        $this->workspace = $workspace instanceof Workspace
            ? (string) $workspace->id
            : (string) $workspace;

        $this->workspaceId = $this->workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole')
            && $user->hasRole(['superadmin', 'global_admin']);

        // Assert staff belongs to the route workspace
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspaceId) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // View dashboard or export permission required to access Data Export Center
        if (!$isElevatedAdmin && !$user->can('dashboard_reports.view_dashboard')) {
            App::abort(403, 'Forbidden: dashboard_reports.view_dashboard permission required.');
        }
    }

    /**
     * Reset status filter when selected entity tab changes.
     */
    public function updatedEntity(): void
    {
        $this->statusFilter = 'all';
    }

    /**
     * Apply date range preset.
     */
    public function applyDatePreset(string $preset): void
    {
        $this->datePreset = $preset;

        switch ($preset) {
            case 'today':
                $this->dateFrom = Carbon::today()->toDateString();
                $this->dateTo = Carbon::today()->toDateString();
                break;
            case '7_days':
                $this->dateFrom = Carbon::today()->subDays(7)->toDateString();
                $this->dateTo = Carbon::today()->toDateString();
                break;
            case '30_days':
                $this->dateFrom = Carbon::today()->subDays(30)->toDateString();
                $this->dateTo = Carbon::today()->toDateString();
                break;
            case '90_days':
                $this->dateFrom = Carbon::today()->subDays(90)->toDateString();
                $this->dateTo = Carbon::today()->toDateString();
                break;
            case 'this_month':
                $this->dateFrom = Carbon::now()->startOfMonth()->toDateString();
                $this->dateTo = Carbon::now()->endOfMonth()->toDateString();
                break;
            case 'this_year':
                $this->dateFrom = Carbon::now()->startOfYear()->toDateString();
                $this->dateTo = Carbon::now()->endOfYear()->toDateString();
                break;
            case 'all':
            default:
                $this->dateFrom = null;
                $this->dateTo = null;
                break;
        }
    }

    /**
     * Compute live estimated record count matching currently selected filters.
     */
    public function getEstimatedCountProperty(): int
    {
        if ($this->entity === 'members') {
            $query = Member::query()->where('workspace_id', $this->workspaceId);

            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }
            if (!empty($this->dateFrom)) {
                $query->whereDate('created_at', '>=', Carbon::parse($this->dateFrom)->toDateString());
            }
            if (!empty($this->dateTo)) {
                $query->whereDate('created_at', '<=', Carbon::parse($this->dateTo)->toDateString());
            }
            if (!empty($this->search)) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $q) use ($term) {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('member_id', 'like', $term)
                        ->orWhere('contact_number', 'like', $term);
                });
            }

            return $query->count();
        }

        if ($this->entity === 'leads') {
            $query = Lead::query()->where('workspace_id', $this->workspaceId);

            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }
            if (!empty($this->dateFrom)) {
                $query->whereDate('submitted_at', '>=', Carbon::parse($this->dateFrom)->toDateString());
            }
            if (!empty($this->dateTo)) {
                $query->whereDate('submitted_at', '<=', Carbon::parse($this->dateTo)->toDateString());
            }
            if (!empty($this->search)) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $q) use ($term) {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('bkash_txid', 'like', $term)
                        ->orWhere('contact_number', 'like', $term);
                });
            }

            return $query->count();
        }

        if ($this->entity === 'invoices') {
            $query = Invoice::query()->where('workspace_id', $this->workspaceId);

            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }
            if (!empty($this->dateFrom)) {
                $query->whereDate('created_at', '>=', Carbon::parse($this->dateFrom)->toDateString());
            }
            if (!empty($this->dateTo)) {
                $query->whereDate('created_at', '<=', Carbon::parse($this->dateTo)->toDateString());
            }
            if (!empty($this->search)) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function (Builder $q) use ($term) {
                    $q->where('invoice_number', 'like', $term)
                        ->orWhere('payment_reference', 'like', $term);
                });
            }

            return $query->count();
        }

        if ($this->entity === 'metrics') {
            return 1;
        }

        return 0;
    }

    /**
     * Dynamic status options based on active entity tab.
     *
     * @return array<string, string>
     */
    public function getStatusOptionsProperty(): array
    {
        return match ($this->entity) {
            'members' => [
                'all' => 'All Statuses',
                'active' => 'Active',
                'inactive' => 'Inactive',
                'restricted' => 'Restricted',
                'revoked' => 'Revoked',
            ],
            'leads' => [
                'all' => 'All Pipeline Stages',
                'submitted' => 'Submitted',
                'under_review' => 'Under Review',
                'missing_items' => 'Missing Items',
                'approved' => 'Approved',
                'converted' => 'Converted',
                'rejected' => 'Rejected',
                'blocked' => 'Blocked',
            ],
            'invoices' => [
                'all' => 'All Billing Statuses',
                'paid' => 'Paid',
                'unpaid' => 'Unpaid',
                'voided' => 'Voided',
            ],
            'metrics' => [
                'all' => 'All Operational Summaries',
            ],
            default => ['all' => 'All'],
        };
    }

    /**
     * Trigger data export stream with permission validation and client download dispatch.
     */
    public function triggerExport(): mixed
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole')
            && $user->hasRole(['superadmin', 'global_admin']);

        // Workspace boundary enforcement
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspaceId) {
            App::abort(403, 'Unauthorized workspace entity.');
        }

        // Entity-specific export permission enforcement
        if (!$isElevatedAdmin) {
            if ($this->entity === 'members' && !$user->can('members.export_csv') && !$user->can('members.export_excel')) {
                App::abort(403, 'Forbidden: members.export_csv permission required.');
            }
            if ($this->entity === 'leads' && !$user->can('leads.view')) {
                App::abort(403, 'Forbidden: leads.view permission required.');
            }
            if ($this->entity === 'invoices' && !$user->can('invoices.export')) {
                App::abort(403, 'Forbidden: invoices.export permission required.');
            }
            if ($this->entity === 'metrics' && !$user->can('dashboard_reports.export_metrics')) {
                App::abort(403, 'Forbidden: dashboard_reports.export_metrics permission required.');
            }
        }

        $routeMap = [
            'members' => 'crm.exports.members',
            'leads' => 'crm.exports.leads',
            'invoices' => 'crm.exports.invoices',
            'metrics' => 'crm.exports.metrics',
        ];

        $routeName = $routeMap[$this->entity] ?? 'crm.exports.members';

        $params = [
            'workspace' => $this->workspaceId,
            'format' => $this->format,
        ];

        if ($this->statusFilter && $this->statusFilter !== 'all') {
            $params['status'] = $this->statusFilter;
        }
        if (!empty($this->dateFrom)) {
            $params['date_from'] = $this->dateFrom;
        }
        if (!empty($this->dateTo)) {
            $params['date_to'] = $this->dateTo;
        }
        if (!empty($this->search)) {
            $params['search'] = $this->search;
        }

        $downloadUrl = route($routeName, $params);

        $this->dispatch('start-export-download', url: $downloadUrl);

        return $this->redirect($downloadUrl, navigate: false);
    }

    public function render(): View
    {
        return view('livewire.reports.data-export-center', [
            'estimatedCount' => $this->estimatedCount,
            'statusOptions' => $this->statusOptions,
        ]);
    }
}

