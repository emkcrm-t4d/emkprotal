<?php

declare(strict_types=1);

namespace App\Livewire\Leads\Modals;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Exceptions\UnmetConversionInvariantException;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadConversionService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ConvertLeadModal extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Lead $lead;

    public ?int $selectedTermId = null;
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

        // Select the first active term by default if available
        $firstTerm = $this->terms->first();
        if ($firstTerm) {
            $this->selectedTermId = (int) $firstTerm->id;
        }
    }

    /**
     * Event listener to open modal.
     * Enforces leads.convert authorization per T-06-09 and DELEGATION-MATRIX.md.
     */
    #[On('openConvertLeadModal')]
    public function openModal(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canConvert = $user->can('leads.convert')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canConvert) {
            App::abort(403, 'Unauthorized: Workspace Manager authority (leads.convert) required.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->lead->workspace_id) {
            App::abort(403, 'Unauthorized workspace entity.');
        }

        $this->resetValidation();

        if ($this->selectedTermId === null) {
            $firstTerm = $this->terms->first();
            if ($firstTerm) {
                $this->selectedTermId = (int) $firstTerm->id;
            }
        }

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
     * Execute atomic 10-step lead conversion to member.
     * Multi-layer defense asserting RBAC authority (T-06-09) and Tri-State invariants (T-06-10).
     */
    public function executeConversion(LeadConversionService $service): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        // T-06-09: Elevation of Privilege defense - leads.convert required
        $canConvert = $user->can('leads.convert')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canConvert) {
            App::abort(403, 'Unauthorized: Workspace Manager authority (leads.convert) required.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->lead->workspace_id) {
            App::abort(403, 'Unauthorized workspace entity.');
        }

        $this->validate([
            'selectedTermId' => ['required', 'integer', 'exists:membership_terms,id'],
        ], [
            'selectedTermId.required' => 'Please select an active membership term.',
            'selectedTermId.exists' => 'The selected membership term is invalid or inactive.',
        ]);

        // T-06-10: Invariant Tampering Gate defense - verify Tri-State invariant before service call
        try {
            $service->assertConversionInvariants($this->lead);
        } catch (UnmetConversionInvariantException $e) {
            $this->addError('conversion', $e->getMessage());
            return;
        }

        try {
            $member = $service->convert($this->lead, (int) $this->selectedTermId, $user);

            $this->lead->refresh();
            $this->showModal = false;

            $message = "Lead successfully converted to Member {$member->member_id}!";
            session()->flash('status', $message);

            $this->dispatch('leadUpdated');
            $this->dispatch('lead-updated');
            $this->dispatch('conversionCompleted', memberId: $member->member_id);
        } catch (UnauthorizedVerificationException $e) {
            App::abort(403, $e->getMessage());
        } catch (UnmetConversionInvariantException $e) {
            $this->addError('conversion', $e->getMessage());
        }
    }

    /**
     * Load active membership terms for workspace.
     */
    #[Computed]
    public function getTermsProperty(): Collection
    {
        $workspaceId = $this->workspace ?: (string) $this->lead->workspace_id;

        $terms = MembershipTerm::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->orderBy('duration_months')
            ->get();

        if ($terms->isEmpty()) {
            $terms = MembershipTerm::where('is_active', true)
                ->orderBy('duration_months')
                ->get();
        }

        return $terms;
    }

    /**
     * Get currently selected membership term entity.
     */
    #[Computed]
    public function getSelectedTermProperty(): ?MembershipTerm
    {
        if (!$this->selectedTermId) {
            return null;
        }

        return $this->terms->firstWhere('id', $this->selectedTermId);
    }

    /**
     * Render the conversion modal view.
     */
    public function render(): View
    {
        return view('livewire.leads.modals.convert-lead-modal', [
            'terms' => $this->terms,
            'selectedTerm' => $this->selectedTerm,
        ]);
    }
}
