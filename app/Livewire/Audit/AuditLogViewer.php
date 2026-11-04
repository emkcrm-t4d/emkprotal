<?php

declare(strict_types=1);

namespace App\Livewire\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class AuditLogViewer extends Component
{
    use WithPagination;
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public string $search = '';
    public string $eventType = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?string $selectedWorkspace = null;

    public ?int $selectedLogId = null;
    public ?AuditLog $selectedLog = null;
    public bool $showDetailModal = false;

    public function mount(?string $workspace = null): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        abort_unless($user->can('platform_services.view_audit_logs'), 403, 'Unauthorized.');

        $this->workspace = $workspace ?? ($user->workspace_id ?? '');

        if (!$user->hasRole(['superadmin', 'global_admin']) && $this->workspace !== (string) $user->workspace_id) {
            App::abort(403, 'Unauthorized workspace access.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingEventType(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedWorkspace(): void
    {
        $this->resetPage();
    }

    public function viewDetails(int $id): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $query = AuditLog::query();
        if (!$user->hasRole(['superadmin', 'global_admin'])) {
            $query->where('workspace_id', $user->workspace_id);
        }

        $log = $query->with(['actor', 'workspace'])->findOrFail($id);

        $this->selectedLogId = $log->id;
        $this->selectedLog = $log;
        $this->showDetailModal = true;
    }

    public function closeDetails(): void
    {
        $this->selectedLogId = null;
        $this->selectedLog = null;
        $this->showDetailModal = false;
    }

    public function render(): View
    {
        $user = Auth::user();
        $isSuperadmin = $user && $user->hasRole(['superadmin', 'global_admin']);

        $query = AuditLog::with(['actor', 'workspace']);

        if ($isSuperadmin) {
            if (!empty($this->selectedWorkspace)) {
                $query->where('workspace_id', $this->selectedWorkspace);
            }
        } else {
            $query->where('workspace_id', $user->workspace_id);
        }

        if (!empty($this->search)) {
            $search = trim($this->search);
            $query->where(function ($q) use ($search) {
                $q->where('auditable_type', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('event_type', 'like', "%{$search}%")
                    ->orWhereHas('actor', function ($actorQuery) use ($search) {
                        $actorQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($this->eventType)) {
            $query->where('event_type', $this->eventType);
        }

        if (!empty($this->dateFrom)) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if (!empty($this->dateTo)) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $auditLogs = $query->orderBy('created_at', 'desc')->paginate(25);

        $workspaces = $isSuperadmin
            ? Workspace::where('is_active', true)->orderBy('name')->get()
            : collect();

        $eventTypes = [
            AuditLog::EVENT_CREATED => 'Entity Created',
            AuditLog::EVENT_UPDATED => 'Entity Updated',
            AuditLog::EVENT_DELETED => 'Entity Deleted',
            AuditLog::EVENT_LOGIN_SUCCESS => 'Login Success',
            AuditLog::EVENT_LOGIN_FAILED => 'Login Failed',
            AuditLog::EVENT_LOGOUT => 'Logout',
            AuditLog::EVENT_PASSWORD_RESET => 'Password Reset',
            AuditLog::EVENT_PERMISSION_DELEGATED => 'Permission Delegated',
            AuditLog::EVENT_PERMISSION_REVOKED => 'Permission Revoked',
            AuditLog::EVENT_DOCUMENT_ACCESSED => 'Document Accessed',
        ];

        return view('livewire.audit.audit-log-viewer', [
            'auditLogs' => $auditLogs,
            'workspaces' => $workspaces,
            'eventTypes' => $eventTypes,
            'isSuperadmin' => $isSuperadmin,
        ]);
    }
}
