<?php

declare(strict_types=1);

namespace App\Livewire\Members\Modals;

use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Exceptions\InvalidConversionReversalException;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\ConversionReversalService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;

class ConversionReversalModal extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public ?int $memberId = null;
    public ?Lead $lead = null;

    public string $justification = '';
    public string $password = '';
    public bool $showModal = false;

    /**
     * Validation rules per CONVERSION-SPEC.md Section 6.
     */
    protected function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:20'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Custom validation messages.
     */
    protected function messages(): array
    {
        return [
            'justification.required' => 'A detailed justification of at least 20 characters is required.',
            'justification.min' => 'The justification must be at least 20 characters.',
            'password.required' => 'Administrative password confirmation is required.',
        ];
    }

    /**
     * Mount component and bind entities.
     */
    public function mount(string $workspace, ?int $memberId = null, ?Lead $lead = null): void
    {
        $this->workspace = $workspace;
        $this->memberId = $memberId;
        $this->lead = $lead;

        if (!$this->memberId && $this->lead && $this->lead->converted_member_id) {
            $this->memberId = (int) $this->lead->converted_member_id;
        }
    }

    /**
     * Event listener to open modal.
     * Enforces leads.reverse_conversion authorization per DELEGATION-MATRIX.md.
     */
    #[On('openConversionReversalModal')]
    public function openModal(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canReverse = $user->can('leads.reverse_conversion')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canReverse) {
            App::abort(403, 'Unauthorized: Workspace Manager authority (leads.reverse_conversion) required.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace entity.');
        }

        if (!$this->memberId && $this->lead && $this->lead->converted_member_id) {
            $this->memberId = (int) $this->lead->converted_member_id;
        }

        $this->justification = '';
        $this->password = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Close modal without taking action.
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->justification = '';
        $this->password = '';
        $this->resetValidation();
    }

    /**
     * Execute conversion reversal with administrative password challenge and justification validation.
     */
    public function executeReversal(ConversionReversalService $service): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        // Layer 1 / Spatie Permission authorization check
        $canReverse = $user->can('leads.reverse_conversion')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canReverse) {
            App::abort(403, 'Unauthorized: Workspace Manager authority (leads.reverse_conversion) required.');
        }

        // Layer 2 / Tenancy defense check
        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace entity.');
        }

        // Validation for justification (min 20 chars) and password
        $this->validate();

        // Administrative re-authentication challenge
        if (!Hash::check($this->password, (string) $user->password)) {
            $this->addError('password', 'Administrative password confirmation failed.');
            return;
        }

        if (!$this->memberId && $this->lead && $this->lead->converted_member_id) {
            $this->memberId = (int) $this->lead->converted_member_id;
        }

        if (!$this->memberId) {
            $this->addError('general', 'No converted member record associated with this lead.');
            return;
        }

        /** @var Member $member */
        $member = Member::findOrFail($this->memberId);

        try {
            $service->reverse($member, $this->justification, $user);
        } catch (InvalidConversionReversalException $e) {
            $this->addError('justification', $e->getMessage());
            return;
        }

        $this->showModal = false;
        $this->dispatch('conversionReversed');
        $this->dispatch('leadUpdated');
        $this->dispatch('lead-updated');

        session()->flash('status', 'Conversion reversed successfully. Lead restored to Under Review.');

        $leadId = $member->lead_id ?? $this->lead?->id;
        if ($leadId) {
            $this->redirect(route('crm.leads.show', [
                'workspace' => $this->workspace,
                'lead' => $leadId,
            ]));
        }
    }

    /**
     * Render the modal view.
     */
    public function render(): View
    {
        $member = $this->memberId ? Member::with(['lead', 'invoices', 'idCards'])->find($this->memberId) : null;

        return view('livewire.members.modals.conversion-reversal-modal', [
            'member' => $member,
        ]);
    }
}
