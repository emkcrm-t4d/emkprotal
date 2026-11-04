<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class MemberDirectory extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    public string $workspace = '';

    // Reactive Filter & Search Parameters
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'term', except: '')]
    public string $termFilter = '';

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortField = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDirection = 'desc';

    /**
     * Mount component asserting authentication, tenancy isolation, and Spatie permission.
     */
    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Assert staff belongs to the route workspace
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Assert members.view permission
        if (!$isElevatedAdmin && !$user->can('members.view')) {
            App::abort(403, 'Forbidden: members.view permission required.');
        }
    }

    /**
     * Hydration guard ensuring workspace context integrity.
     */
    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin'])) {
            if (!empty($this->workspace) && (string) $user->workspace_id !== (string) $this->workspace) {
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

    public function updatingTermFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Sort by specified column.
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /**
     * Reset all search and filter controls.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->termFilter = '';
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';

        $this->resetPage();
    }

    /**
     * Render directory table with reactive query filtering.
     */
    public function render(): View
    {
        $user = Auth::user();
        $isElevatedAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Base metrics query
        $metricsBase = Member::query();
        if ($isElevatedAdmin && !empty($this->workspace)) {
            $metricsBase->where('workspace_id', $this->workspace);
        }

        $metrics = [
            'total' => (clone $metricsBase)->count(),
            'active' => (clone $metricsBase)->where('status', 'active')->count(),
            'inactive' => (clone $metricsBase)->where('status', 'inactive')->count(),
            'restricted' => (clone $metricsBase)->where('status', 'restricted')->count(),
            'revoked' => (clone $metricsBase)->where('status', 'revoked')->count(),
        ];

        // Main table query with term and user loaded
        $query = Member::with(['term', 'user']);

        if ($isElevatedAdmin && !empty($this->workspace)) {
            $query->where('workspace_id', $this->workspace);
        }

        // Keyword search across member_id, full name, email, or contact_number
        if (!empty(trim($this->search))) {
            $term = trim($this->search);
            $searchTerm = '%' . $term . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('member_id', 'like', $searchTerm)
                    ->orWhere('first_name', 'like', $searchTerm)
                    ->orWhere('middle_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('contact_number', 'like', $searchTerm);
            });
        }

        // Status filter
        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        // Membership term filter
        if (!empty($this->termFilter)) {
            $query->where('membership_term_id', $this->termFilter);
        }

        // Sorting
        $validSortFields = ['created_at', 'member_id', 'first_name', 'last_name', 'email', 'status', 'start_date', 'expiry_date'];
        $field = in_array($this->sortField, $validSortFields, true) ? $this->sortField : 'created_at';
        $direction = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($field, $direction);

        $members = $query->paginate(15);

        // Fetch terms for dropdown
        $termsQuery = MembershipTerm::query();
        if ($isElevatedAdmin && !empty($this->workspace)) {
            $termsQuery->where('workspace_id', $this->workspace);
        }
        $terms = $termsQuery->orderBy('duration_months')->get();

        return view('livewire.members.member-directory', [
            'members' => $members,
            'metrics' => $metrics,
            'terms' => $terms,
            'activeModule' => 'members',
        ])->layout('layouts.admin', [
            'activeModule' => 'members',
            'title' => 'Member Management Directory - ' . $this->workspace,
        ]);
    }
}
