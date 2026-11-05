<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Reports\Services\MetricsAggregationService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Domain\Workspaces\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
class OperationalDashboard extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';

    public string $workspaceId = '';

    #[Url(as: 'preset', except: '30_days')]
    public string $datePreset = '30_days';

    #[Url(as: 'from', except: '')]
    public ?string $dateFrom = null;

    #[Url(as: 'to', except: '')]
    public ?string $dateTo = null;

    #[Url(as: 'global', except: false)]
    public bool $isGlobalView = false;

    #[Url(as: 'scope', except: '')]
    public ?string $selectedWorkspaceScope = null;

    public bool $canSwitchScope = false;

    /**
     * Mount component asserting authentication, tenancy isolation, and Spatie permission.
     */
    public function mount(mixed $workspace): void
    {
        $this->workspace = $workspace instanceof Workspace
            ? (string) $workspace->id
            : (string) $workspace;

        $this->workspaceId = $this->workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole')
            && $user->hasRole(['superadmin', 'global_admin']);

        $this->canSwitchScope = $isElevatedAdmin;

        // Assert staff belongs to the route workspace
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspaceId) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Assert dashboard_reports.view_dashboard permission
        if (!$isElevatedAdmin && !$user->can('dashboard_reports.view_dashboard')) {
            App::abort(403, 'Forbidden: dashboard_reports.view_dashboard permission required.');
        }

        // Non-superadmin cannot open in global view or switch scope
        if (!$isElevatedAdmin) {
            $this->isGlobalView = false;
            $this->selectedWorkspaceScope = $this->workspaceId;
        } else {
            if (empty($this->selectedWorkspaceScope)) {
                $this->selectedWorkspaceScope = $this->workspaceId;
            }
        }

        // Initialize date range based on preset if from/to not set via URL
        if (empty($this->dateFrom) && empty($this->dateTo)) {
            $this->applyDatePreset($this->datePreset);
        }
    }

    /**
     * Intercept hydration to strictly fail closed against workspace tampering.
     */
    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin'])) {
            if (!empty($this->workspaceId) && (string) $user->workspace_id !== (string) $this->workspaceId) {
                App::abort(403, 'Unauthorized workspace access.');
            }
            if (!empty($this->selectedWorkspaceScope) && (string) $user->workspace_id !== (string) $this->selectedWorkspaceScope) {
                App::abort(403, 'Unauthorized: Switching workspace scope requires Superadmin privileges.');
            }
            if ($this->isGlobalView) {
                App::abort(403, 'Unauthorized: Multi-workspace aggregation requires Superadmin privileges.');
            }
        }
    }

    /**
     * Intercept Livewire property updates on selectedWorkspaceScope to prevent unauthorized tampering.
     */
    public function updatingSelectedWorkspaceScope(mixed $value): void
    {
        $user = Auth::user();
        if (!$this->canSwitchScope || ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin']))) {
            App::abort(403, 'Unauthorized: Switching workspace scope requires Superadmin privileges.');
        }
    }

    /**
     * Intercept Livewire property updates on isGlobalView to prevent unauthorized tampering.
     */
    public function updatingIsGlobalView(mixed $value): void
    {
        $user = Auth::user();
        if (!$this->canSwitchScope || ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin']))) {
            App::abort(403, 'Unauthorized: Multi-workspace aggregation requires Superadmin privileges.');
        }
    }

    /**
     * Intercept Livewire property updates on workspaceId to prevent unauthorized tampering.
     */
    public function updatingWorkspaceId(mixed $value): void
    {
        $user = Auth::user();
        if (!$this->canSwitchScope || ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin']))) {
            App::abort(403, 'Unauthorized workspace access.');
        }
    }

    /**
     * Apply a predefined date preset (7_days, 30_days, 90_days, year, all, custom).
     */
    public function applyDatePreset(string $preset): void
    {
        $this->datePreset = $preset;

        match ($preset) {
            '7_days' => [
                $this->dateFrom = Carbon::now()->subDays(7)->toDateString(),
                $this->dateTo = Carbon::now()->toDateString(),
            ],
            '30_days' => [
                $this->dateFrom = Carbon::now()->subDays(30)->toDateString(),
                $this->dateTo = Carbon::now()->toDateString(),
            ],
            '90_days' => [
                $this->dateFrom = Carbon::now()->subDays(90)->toDateString(),
                $this->dateTo = Carbon::now()->toDateString(),
            ],
            'year' => [
                $this->dateFrom = Carbon::now()->startOfYear()->toDateString(),
                $this->dateTo = Carbon::now()->toDateString(),
            ],
            'all' => [
                $this->dateFrom = null,
                $this->dateTo = null,
            ],
            default => null, // custom preserves existing dates
        };
    }

    /**
     * Transition preset to custom when date inputs are modified directly.
     */
    public function updatedDateFrom(): void
    {
        $this->datePreset = 'custom';
    }

    /**
     * Transition preset to custom when date inputs are modified directly.
     */
    public function updatedDateTo(): void
    {
        $this->datePreset = 'custom';
    }

    /**
     * Invalidate cache and recompute metrics.
     */
    public function refreshMetrics(): void
    {
        /** @var MetricsAggregationService $service */
        $service = app(MetricsAggregationService::class);
        $targetWorkspace = $this->selectedWorkspaceScope ?: $this->workspaceId;
        $service->clearMetricsCache($targetWorkspace);
    }

    /**
     * Toggle between single-workspace scope and global multi-workspace consolidated view.
     */
    public function toggleGlobalView(bool $enableGlobal): void
    {
        if (!$this->canSwitchScope) {
            App::abort(403, 'Unauthorized: Multi-workspace aggregation requires Superadmin privileges.');
        }

        $this->isGlobalView = $enableGlobal;
    }

    /**
     * Switch active single-workspace scope (privileged Superadmin action).
     */
    public function switchWorkspaceScope(string $newWorkspaceId): void
    {
        if (!$this->canSwitchScope) {
            App::abort(403, 'Unauthorized: Switching workspace scope requires Superadmin privileges.');
        }

        $this->selectedWorkspaceScope = $newWorkspaceId;
        $this->isGlobalView = false;
    }

    /**
     * Retrieve available workspaces for the superadmin switcher.
     *
     * @return array<int, array<string, string>>
     */
    public function getAvailableWorkspaces(): array
    {
        if (!$this->canSwitchScope) {
            return [];
        }

        return DB::table('workspaces')
            ->leftJoin('spaces', 'workspaces.space_id', '=', 'spaces.id')
            ->select([
                'workspaces.id as id',
                'workspaces.name as name',
                'spaces.code as space_code',
            ])
            ->orderBy('workspaces.id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (string) $row->id,
                    'name' => (string) ($row->name ?? $row->id),
                    'code' => (string) ($row->space_code ?? $row->id),
                ];
            })
            ->all();
    }

    /**
     * Render the Livewire component view.
     */
    public function render(): View
    {
        /** @var MetricsAggregationService $service */
        $service = app(MetricsAggregationService::class);

        $metrics = null;
        $globalMetrics = null;

        if ($this->isGlobalView && $this->canSwitchScope) {
            $globalMetrics = $service->getGlobalMetrics($this->dateFrom, $this->dateTo);
        } else {
            $targetWorkspace = $this->selectedWorkspaceScope ?: $this->workspaceId;
            $metrics = $service->getMetrics($targetWorkspace, $this->dateFrom, $this->dateTo);
        }

        return view('livewire.reports.operational-dashboard', [
            'metrics' => $metrics,
            'globalMetrics' => $globalMetrics,
            'availableWorkspaces' => $this->getAvailableWorkspaces(),
        ])->title('Operational Metrics & Analytics');
    }
}
