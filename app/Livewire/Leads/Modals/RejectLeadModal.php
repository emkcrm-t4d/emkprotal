<?php

declare(strict_types=1);

namespace App\Livewire\Leads\Modals;

use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadCommunicationService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class RejectLeadModal extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Lead $lead;

    public string $rejectionReasonCode = '';
    public string $internalNotes = '';
    public bool $blockEmail = false;
    public bool $showModal = false;

    /**
     * Mount component and bind entity.
     */
    public function mount(Lead|int|string $lead, string $workspace): void
    {
        $this->workspace = $workspace;

        if (!($lead instanceof Lead)) {
            $lead = Lead::findOrFail($lead);
        }

        $this->lead = $lead;
    }

    /**
     * Event listener to open rejection modal.
     */
    #[On('openRejectLeadModal')]
    public function openModal(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canReject = $user->can('leads.reject')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canReject) {
            App::abort(403, 'Unauthorized: Workspace Manager authority (leads.reject) required.');
        }

        $this->resetValidation();
        $this->rejectionReasonCode = '';
        $this->internalNotes = '';
        $this->blockEmail = false;
        $this->showModal = true;
    }

    /**
     * Close modal without saving.
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    /**
     * Confirm application rejection and optionally block applicant email.
     */
    public function confirmRejection(LeadCommunicationService $service): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canReject = $user->can('leads.reject')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canReject) {
            App::abort(403, 'Unauthorized: Workspace Manager authority (leads.reject) required.');
        }

        $this->validate([
            'rejectionReasonCode' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(LeadCommunicationService::getRejectionTaxonomy())),
            ],
            'internalNotes' => ['nullable', 'string', 'max:1000'],
        ], [
            'rejectionReasonCode.required' => 'Please select a standardized rejection reason from the taxonomy.',
            'rejectionReasonCode.in' => 'Selected rejection reason is invalid.',
        ]);

        try {
            // 1. Reject lead and send official rejection mail
            $service->rejectLead(
                $this->lead,
                $user,
                $this->rejectionReasonCode,
                $this->internalNotes ?: null
            );

            // 2. If block email is toggled by Manager, restrict applicant email
            if ($this->blockEmail) {
                $justification = $this->internalNotes ?: "Email blocked due to fraudulent/abusive submission ({$this->rejectionReasonCode}).";
                $service->blockApplicantEmail(
                    $this->lead,
                    $user,
                    $justification
                );
            }

            $this->lead->refresh();
            $this->showModal = false;

            $statusMessage = $this->blockEmail
                ? 'Application formally rejected and applicant email blocked from future submissions.'
                : 'Application formally rejected and notification email sent to applicant.';

            session()->flash('status', $statusMessage);
            $this->dispatch('leadUpdated');
            $this->dispatch('lead-updated');
        } catch (UnauthorizedVerificationException $e) {
            App::abort(403, $e->getMessage());
        }
    }

    /**
     * Get selected taxonomy entry.
     */
    public function getSelectedTaxonomyProperty(): ?array
    {
        return LeadCommunicationService::getRejectionTaxonomy()[$this->rejectionReasonCode] ?? null;
    }

    /**
     * Render the rejection modal view.
     */
    public function render(): View
    {
        return view('livewire.leads.modals.reject-lead-modal', [
            'taxonomy' => LeadCommunicationService::getRejectionTaxonomy(),
            'selectedTaxonomy' => $this->selectedTaxonomy,
        ]);
    }
}
