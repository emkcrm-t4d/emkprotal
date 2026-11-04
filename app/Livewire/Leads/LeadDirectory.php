<?php

declare(strict_types=1);

namespace App\Livewire\Leads;

use App\Domain\Leads\Models\Lead;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class LeadDirectory extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    public string $workspace = '';

    // Reactive Filter & Search Parameters
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'payment', except: '')]
    public string $paymentFilter = '';

    #[Url(as: 'document', except: '')]
    public string $documentFilter = '';

    #[Url(as: 'photo', except: '')]
    public string $photoFilter = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    #[Url(as: 'sort', except: 'submitted_at')]
    public string $sortField = 'submitted_at';

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

        // Assert leads.view permission
        if (!$isElevatedAdmin && !$user->can('leads.view')) {
            App::abort(403, 'Forbidden: leads.view permission required.');
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

    public function updatingPaymentFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDocumentFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPhotoFilter(): void
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
        $this->paymentFilter = '';
        $this->documentFilter = '';
        $this->photoFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->sortField = 'submitted_at';
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
        $metricsBase = Lead::query();
        if ($isElevatedAdmin && !empty($this->workspace)) {
            $metricsBase->where('workspace_id', $this->workspace);
        }

        $metrics = [
            'total' => (clone $metricsBase)->count(),
            'submitted' => (clone $metricsBase)->where('status', 'submitted')->count(),
            'under_review' => (clone $metricsBase)->where('status', 'under_review')->count(),
            'missing_items' => (clone $metricsBase)->where('status', 'missing_items')->count(),
            'approved' => (clone $metricsBase)->where('status', 'approved')->count(),
            'converted' => (clone $metricsBase)->where('status', 'converted')->count(),
            'rejected' => (clone $metricsBase)->where('status', 'rejected')->count(),
        ];

        // Main table query with attachments loaded
        $query = Lead::with('attachments');

        if ($isElevatedAdmin && !empty($this->workspace)) {
            $query->where('workspace_id', $this->workspace);
        }

        // Keyword search across applicant name, email, contact number, or bKash TxID
        if (!empty(trim($this->search))) {
            $term = trim($this->search);
            $searchTerm = '%' . $term . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('contact_number', 'like', $searchTerm)
                    ->orWhere('bkash_txid', 'like', $searchTerm);
            });
        }

        // Lead lifecycle status filter
        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        // Independent tri-state verification stream filters
        if (!empty($this->paymentFilter)) {
            $query->where('payment_verification_status', $this->paymentFilter);
        }

        if (!empty($this->documentFilter)) {
            $query->where('document_verification_status', $this->documentFilter);
        }

        if (!empty($this->photoFilter)) {
            $query->where('photo_verification_status', $this->photoFilter);
        }

        // Submitted date bounds
        if (!empty($this->dateFrom)) {
            $query->whereDate('submitted_at', '>=', $this->dateFrom);
        }

        if (!empty($this->dateTo)) {
            $query->whereDate('submitted_at', '<=', $this->dateTo);
        }

        // Sorting
        $validSortFields = ['submitted_at', 'first_name', 'last_name', 'status', 'email'];
        $field = in_array($this->sortField, $validSortFields, true) ? $this->sortField : 'submitted_at';
        $direction = strtolower($this->sortDirection) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($field, $direction);

        $leads = $query->paginate(15);

        return view('livewire.leads.lead-directory', [
            'leads' => $leads,
            'metrics' => $metrics,
            'activeModule' => 'leads',
        ])->layout('layouts.admin', [
            'activeModule' => 'leads',
            'title' => 'Lead Management Directory - ' . $this->workspace,
        ]);
    }
}
