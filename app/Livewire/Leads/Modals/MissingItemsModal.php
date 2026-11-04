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

class MissingItemsModal extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Lead $lead;

    public array $selectedItems = [];
    public string $customInstructions = '';
    public bool $showModal = false;

    /**
     * Standard common missing items checklist.
     */
    public const CHECKLIST_OPTIONS = [
        'Document Requirements' => [
            'National ID front side unreadable/cropped',
            'National ID back side missing',
            'Passport data page blurred/expired',
            'Signed Parental/Guardian Consent Form missing (under-18 applicant)',
        ],
        'Photo Requirements' => [
            'Background is textured/coloured (must be solid white)',
            'Eyeglasses or sunglasses present (must be removed)',
            'Both ears not clearly visible',
            'Casual selfie or filtered photo (must be studio portrait)',
        ],
        'Payment Requirements' => [
            'bKash Transaction ID unverified or amount mismatch',
        ],
    ];

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
     * Event listener to open modal.
     */
    #[On('openMissingItemsModal')]
    public function openModal(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canCommunicate = $user->can('leads.communicate')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canCommunicate) {
            App::abort(403, 'Unauthorized: leads.communicate permission required.');
        }

        $this->resetValidation();
        $this->selectedItems = [];
        $this->customInstructions = '';
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
     * Dispatch official missing items notification email and mark lead.
     */
    public function dispatchMissingItemsNotification(LeadCommunicationService $service): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canCommunicate = $user->can('leads.communicate')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canCommunicate) {
            App::abort(403, 'Unauthorized: leads.communicate permission required.');
        }

        if (empty($this->selectedItems) && empty(trim($this->customInstructions))) {
            $this->addError('selectedItems', 'Please select at least one outstanding item or provide instructions.');
            return;
        }

        try {
            $service->sendMissingItemsNotification(
                $this->lead,
                $user,
                $this->selectedItems,
                $this->customInstructions ?: null
            );

            $this->lead->refresh();
            $this->showModal = false;

            session()->flash('status', 'Official missing items notification dispatched to applicant (' . $this->lead->email . ').');
            $this->dispatch('leadUpdated');
            $this->dispatch('lead-updated');
        } catch (UnauthorizedVerificationException $e) {
            App::abort(403, $e->getMessage());
        }
    }

    /**
     * Render the missing items modal view.
     */
    public function render(): View
    {
        return view('livewire.leads.modals.missing-items-modal', [
            'checklistOptions' => self::CHECKLIST_OPTIONS,
        ]);
    }
}
