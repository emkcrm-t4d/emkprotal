<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Modals;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\MemberRenewalService;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SubmitRenewalModal extends Component
{
    public Member $member;
    public bool $isOpen = false;
    public bool $isRenewalOpen = false;
    public bool $hasPendingRequest = false;
    public ?string $submissionError = null;

    public ?int $selected_term_id = null;
    public string $bkash_txid = '';

    /**
     * Mount component and check renewal window eligibility.
     */
    public function mount(Member $member): void
    {
        $this->member = $member;
        $this->checkEligibility();
        $this->initializeTermSelection();
    }

    /**
     * Check if member is eligible to renew and if a pending request exists.
     */
    public function checkEligibility(): void
    {
        $service = app(MemberRenewalService::class);
        $this->isRenewalOpen = $service->isRenewalWindowOpen($this->member);

        $this->hasPendingRequest = MemberChangeRequest::withoutGlobalScopes()
            ->where('member_id', $this->member->id)
            ->where('request_type', MemberChangeRequest::TYPE_RENEWAL)
            ->where('status', MemberChangeRequest::STATUS_PENDING)
            ->exists();
    }

    /**
     * Pre-select active membership term matching current term or first available.
     */
    public function initializeTermSelection(): void
    {
        $terms = MembershipTerm::withoutGlobalScopes()
            ->where('workspace_id', $this->member->workspace_id)
            ->where('is_active', true)
            ->orderBy('duration_months')
            ->get();

        if ($terms->isNotEmpty()) {
            $matchingTerm = $terms->firstWhere('id', $this->member->membership_term_id);
            $this->selected_term_id = $matchingTerm ? $matchingTerm->id : $terms->first()->id;
        }
    }

    /**
     * Livewire validation rules for renewal submission per DEC-05.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'selected_term_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail): void {
                    $exists = MembershipTerm::withoutGlobalScopes()
                        ->where('id', $value)
                        ->where('workspace_id', $this->member->workspace_id)
                        ->where('is_active', true)
                        ->exists();

                    if (! $exists) {
                        $fail('Please select a valid active membership term.');
                    }
                },
            ],
            'bkash_txid' => [
                'required',
                'string',
                'size:10',
                'regex:/^[A-Z0-9]{10}$/',
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'selected_term_id.required' => 'Please select a membership term for renewal.',
            'bkash_txid.required' => 'bKash Transaction ID is required to verify your renewal payment.',
            'bkash_txid.size' => 'The bKash Transaction ID must be exactly 10 characters.',
            'bkash_txid.regex' => 'The bKash Transaction ID must consist of 10 uppercase letters and digits.',
        ];
    }

    /**
     * Transform bKash TxID to uppercase on input and validate.
     */
    public function updatedBkashTxid(string $value): void
    {
        $this->bkash_txid = strtoupper(trim($value));
        $this->validateOnly('bkash_txid');
    }

    /**
     * Open modal listener.
     */
    #[On('openSubmitRenewalModal')]
    public function openModal(): void
    {
        $this->checkEligibility();
        $this->initializeTermSelection();
        $this->bkash_txid = '';
        $this->submissionError = null;
        $this->resetValidation();
        $this->isOpen = true;
    }

    /**
     * Close modal dialog.
     */
    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetValidation();
        $this->submissionError = null;
    }

    /**
     * Submit renewal request for staff payment verification.
     */
    public function submit(MemberRenewalService $service): void
    {
        $this->submissionError = null;
        $this->checkEligibility();

        if (! $this->isRenewalOpen) {
            $this->submissionError = 'Renewal window is not open. You can renew starting 30 days prior to your expiration date.';
            return;
        }

        if ($this->hasPendingRequest) {
            $this->submissionError = 'A renewal request is already pending review for this membership.';
            return;
        }

        $this->validate();

        try {
            $service->submitRenewalRequest(
                $this->member,
                (int) $this->selected_term_id,
                $this->bkash_txid
            );

            $this->isOpen = false;
            $this->hasPendingRequest = true;
            $this->dispatch('renewal-submitted');
            session()->flash('status', 'Renewal request submitted. Workspace staff will verify your bKash payment and update your validity.');
        } catch (DomainException $e) {
            $this->submissionError = $e->getMessage();
            $this->addError('bkash_txid', $e->getMessage());
        }
    }

    /**
     * Render modal view.
     */
    public function render(): View
    {
        $terms = MembershipTerm::withoutGlobalScopes()
            ->where('workspace_id', $this->member->workspace_id)
            ->where('is_active', true)
            ->orderBy('duration_months')
            ->get();

        $selectedTerm = $terms->firstWhere('id', $this->selected_term_id);

        return view('livewire.portal.modals.submit-renewal-modal', [
            'terms' => $terms,
            'selectedTerm' => $selectedTerm,
        ]);
    }
}
