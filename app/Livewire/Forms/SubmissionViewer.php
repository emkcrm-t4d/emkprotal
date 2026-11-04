<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class SubmissionViewer extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    public string $workspace = '';
    public Form $form;

    // Reactive Filter State
    public string $search = '';
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?string $selectedWorkspace = null;

    // Inspector Modal State
    public bool $showModal = false;
    public ?int $selectedSubmissionId = null;
    public ?FormSubmission $selectedSubmission = null;

    public function mount(string $workspace, Form|int|string $form): void
    {
        $this->workspace = $workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isSuperadmin = $user->hasRole(['superadmin', 'global_admin']);

        if (!$isSuperadmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        if (!$user->can('forms.view_entries') && !$isSuperadmin) {
            App::abort(403, 'Forbidden: forms.view_entries permission required.');
        }

        if (!($form instanceof Form)) {
            $formQuery = Form::query();
            if (!$isSuperadmin) {
                $formQuery->where('workspace_id', $this->workspace);
            }
            $form = $formQuery->findOrFail($form);
        } else {
            if (!$isSuperadmin && (string) $form->workspace_id !== (string) $this->workspace) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
        }

        $this->form = $form;
    }

    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && !$user->hasRole(['superadmin', 'global_admin'])) {
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

    public function inspectSubmission(int|string $submissionId): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isSuperadmin = $user->hasRole(['superadmin', 'global_admin']);

        $query = FormSubmission::with(['lead.attachments', 'form', 'workspace'])
            ->where('form_id', $this->form->id);

        if (!$isSuperadmin) {
            $query->where('workspace_id', $this->workspace);
        }

        $submission = $query->findOrFail($submissionId);

        $this->selectedSubmissionId = $submission->id;
        $this->selectedSubmission = $submission;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->selectedSubmissionId = null;
        $this->selectedSubmission = null;
        $this->showModal = false;
    }

    public function render(): View
    {
        $user = Auth::user();
        $isSuperadmin = $user && $user->hasRole(['superadmin', 'global_admin']);

        // Base metrics query
        $metricsQuery = FormSubmission::where('form_id', $this->form->id);
        if (!$isSuperadmin) {
            $metricsQuery->where('workspace_id', $this->workspace);
        } elseif (!empty($this->selectedWorkspace)) {
            $metricsQuery->where('workspace_id', $this->selectedWorkspace);
        }

        $totalSubmissions = (clone $metricsQuery)->count();
        $pendingTriage = (clone $metricsQuery)->where('status', 'submitted')->count();
        $processedLeads = (clone $metricsQuery)->where('status', 'processed')->count();
        $failedSubmissions = (clone $metricsQuery)->where('status', 'failed')->count();

        // Main table query
        $query = FormSubmission::with(['lead.attachments', 'workspace'])
            ->where('form_id', $this->form->id);

        if (!$isSuperadmin) {
            $query->where('workspace_id', $this->workspace);
        } elseif (!empty($this->selectedWorkspace)) {
            $query->where('workspace_id', $this->selectedWorkspace);
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->dateFrom)) {
            $query->whereDate('submitted_at', '>=', $this->dateFrom);
        }
        if (!empty($this->dateTo)) {
            $query->whereDate('submitted_at', '<=', $this->dateTo);
        }

        if (!empty(trim($this->search))) {
            $term = trim($this->search);
            $searchTerm = '%' . $term . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('lead', function ($lq) use ($searchTerm) {
                    $lq->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhere('contact_number', 'like', $searchTerm)
                        ->orWhere('bkash_txid', 'like', $searchTerm);
                })
                ->orWhere('payload->first_name', 'like', $searchTerm)
                ->orWhere('payload->last_name', 'like', $searchTerm)
                ->orWhere('payload->email', 'like', $searchTerm)
                ->orWhere('payload->contact_number', 'like', $searchTerm)
                ->orWhere('payload->bkash_txid', 'like', $searchTerm);
            });
        }

        $submissions = $query->orderByDesc('submitted_at')->paginate(15);

        $workspaces = $isSuperadmin
            ? Workspace::where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('livewire.forms.submission-viewer', [
            'submissions' => $submissions,
            'metrics' => [
                'total' => $totalSubmissions,
                'pending' => $pendingTriage,
                'processed' => $processedLeads,
                'failed' => $failedSubmissions,
            ],
            'workspaces' => $workspaces,
            'isSuperadmin' => $isSuperadmin,
        ]);
    }
}
