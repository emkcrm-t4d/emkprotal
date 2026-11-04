<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\MemberChangeRequestService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class MemberChangeRequestDirectory extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    public string $workspace = '';

    #[Url(as: 'status', except: 'pending')]
    public string $statusFilter = 'pending';

    #[Url(as: 'type', except: 'all')]
    public string $typeFilter = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    // Diff Inspector Modal state
    public bool $isDiffModalOpen = false;
    public ?int $selectedRequestId = null;
    public string $rejectionNotes = '';
    public ?string $errorMessage = null;

    /**
     * Mount component asserting authentication, tenancy isolation, and Spatie permission.
     */
    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;

        $user = Auth::user();
        if (! $user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Tenancy check: staff must belong to this workspace
        if (! $isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Permission check: members.view required
        if (! $isElevatedAdmin && ! $user->can('members.view')) {
            App::abort(403, 'Forbidden: members.view permission required.');
        }
    }

    /**
     * Hydration guard ensuring workspace context integrity.
     */
    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && ! $user->hasRole(['superadmin', 'global_admin'])) {
            if (! empty($this->workspace) && (string) $user->workspace_id !== (string) $this->workspace) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Reset all filters to default.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'pending';
        $this->typeFilter = 'all';
        $this->resetPage();
    }

    /**
     * Open diff inspector modal for a specific change request.
     */
    public function openDiffModal(int $requestId): void
    {
        $user = Auth::user();
        $isElevatedAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        $query = MemberChangeRequest::withoutGlobalScopes()->with(['member', 'reviewer']);
        if (! $isElevatedAdmin) {
            $query->where('workspace_id', $this->workspace);
        }

        $request = $query->findOrFail($requestId);

        $this->selectedRequestId = $request->id;
        $this->rejectionNotes = '';
        $this->errorMessage = null;
        $this->isDiffModalOpen = true;
    }

    /**
     * Close diff inspector modal.
     */
    public function closeDiffModal(): void
    {
        $this->isDiffModalOpen = false;
        $this->selectedRequestId = null;
        $this->rejectionNotes = '';
        $this->errorMessage = null;
    }

    /**
     * Approve selected change request.
     */
    public function approve(MemberChangeRequestService $service): void
    {
        $this->errorMessage = null;

        if (! $this->selectedRequestId) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            App::abort(401, 'Unauthenticated');
        }

        $changeRequest = MemberChangeRequest::withoutGlobalScopes()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($this->selectedRequestId);

        try {
            if ($changeRequest->request_type === MemberChangeRequest::TYPE_EMAIL_CHANGE) {
                $service->approveEmailChangeRequest($changeRequest, $user);

                $this->closeDiffModal();
                session()->flash('status', 'Member email successfully updated across portal credentials and member profile.');
            } else {
                $service->approveRequest($changeRequest, $user);

                $this->closeDiffModal();
                session()->flash('status', 'Change request successfully approved and member profile updated.');
            }
        } catch (AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Reject selected change request with manager notes.
     */
    public function reject(MemberChangeRequestService $service): void
    {
        $this->errorMessage = null;

        if (! $this->selectedRequestId) {
            return;
        }

        $this->validate([
            'rejectionNotes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $user = Auth::user();
        if (! $user) {
            App::abort(401, 'Unauthenticated');
        }

        $changeRequest = MemberChangeRequest::withoutGlobalScopes()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($this->selectedRequestId);

        try {
            $service->rejectRequest($changeRequest, $user, $this->rejectionNotes);

            $this->closeDiffModal();
            session()->flash('status', 'Change request has been rejected.');
        } catch (AuthorizationException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Render directory view with change requests and diff inspector.
     */
    public function render(): View
    {
        $user = Auth::user();
        $isElevatedAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Metrics base
        $metricsBase = MemberChangeRequest::withoutGlobalScopes();
        if (! $isElevatedAdmin || ! empty($this->workspace)) {
            $metricsBase->where('workspace_id', $this->workspace);
        }

        $metrics = [
            'total' => (clone $metricsBase)->count(),
            'pending' => (clone $metricsBase)->where('status', MemberChangeRequest::STATUS_PENDING)->count(),
            'approved' => (clone $metricsBase)->where('status', MemberChangeRequest::STATUS_APPROVED)->count(),
            'rejected' => (clone $metricsBase)->where('status', MemberChangeRequest::STATUS_REJECTED)->count(),
        ];

        // Directory list query
        $query = MemberChangeRequest::withoutGlobalScopes()
            ->with(['member', 'reviewer']);

        if (! $isElevatedAdmin || ! empty($this->workspace)) {
            $query->where('workspace_id', $this->workspace);
        }

        if ($this->statusFilter !== 'all' && in_array($this->statusFilter, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter !== 'all' && in_array($this->typeFilter, ['general_update', 'email_change', 'renewal'], true)) {
            $query->where('request_type', $this->typeFilter);
        }

        if (! empty(trim($this->search))) {
            $term = trim($this->search);
            $searchTerm = '%' . $term . '%';

            $query->whereHas('member', function ($q) use ($searchTerm) {
                $q->where('member_id', 'like', $searchTerm)
                    ->orWhere('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm);
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(15);

        $selectedRequest = null;
        if ($this->isDiffModalOpen && $this->selectedRequestId) {
            $selectedRequest = MemberChangeRequest::withoutGlobalScopes()
                ->with(['member', 'reviewer'])
                ->find($this->selectedRequestId);
        }

        $isElevatedOrManager = $user && (
            (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']))
        );

        $canApproveOrReject = $user && (
            $isElevatedOrManager || $user->can('members.edit')
        );

        $canApproveSelected = $selectedRequest && $selectedRequest->request_type === MemberChangeRequest::TYPE_EMAIL_CHANGE
            ? $isElevatedOrManager
            : $canApproveOrReject;

        return view('livewire.members.member-change-request-directory', [
            'requests' => $requests,
            'metrics' => $metrics,
            'selectedRequest' => $selectedRequest,
            'canApproveOrReject' => $canApproveOrReject,
            'canApproveSelected' => $canApproveSelected,
            'isElevatedOrManager' => $isElevatedOrManager,
            'activeModule' => 'members',
        ])->layout('layouts.admin', [
            'activeModule' => 'members',
            'title' => 'Member Change Requests - ' . $this->workspace,
        ]);
    }
}
