<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Modals;

use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\MemberChangeRequestService;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SubmitEmailChangeModal extends Component
{
    public Member $member;
    public bool $isOpen = false;
    public bool $hasPendingRequest = false;
    public ?string $submissionError = null;

    public string $new_email = '';
    public string $new_email_confirmation = '';
    public string $justification = '';

    /**
     * Mount component asserting member entity.
     */
    public function mount(Member $member): void
    {
        $this->member = $member;
        $this->checkPendingRequest();
    }

    /**
     * Check if an email change request is already pending review.
     */
    public function checkPendingRequest(): void
    {
        $this->hasPendingRequest = MemberChangeRequest::withoutGlobalScopes()
            ->where('member_id', $this->member->id)
            ->where('request_type', MemberChangeRequest::TYPE_EMAIL_CHANGE)
            ->where('status', MemberChangeRequest::STATUS_PENDING)
            ->exists();
    }

    /**
     * Livewire validation rules including cross-table uniqueness check.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'new_email' => [
                'required',
                'string',
                'email',
                'max:255',
                function ($attribute, $value, $fail): void {
                    $normalized = strtolower(trim((string) $value));
                    if ($normalized === strtolower(trim((string) $this->member->email))) {
                        $fail('The new email address must differ from your current registered email.');
                        return;
                    }

                    $existsInMembers = Member::withoutGlobalScopes()
                        ->whereRaw('LOWER(email) = ?', [$normalized])
                        ->exists();

                    $existsInUsers = User::withoutGlobalScopes()
                        ->whereRaw('LOWER(email) = ?', [$normalized])
                        ->exists();

                    if ($existsInMembers || $existsInUsers) {
                        $fail('The requested email address is already in use by another account.');
                    }
                },
            ],
            'new_email_confirmation' => ['required', 'same:new_email'],
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
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
            'new_email.required' => 'Please provide the proposed new email address.',
            'new_email.email' => 'Please enter a valid email address.',
            'new_email_confirmation.required' => 'Please confirm your new email address.',
            'new_email_confirmation.same' => 'The email confirmation does not match the proposed email.',
            'justification.required' => 'A valid justification is required for security review.',
            'justification.min' => 'Justification must be at least 10 characters explaining why the email change is necessary.',
        ];
    }

    /**
     * Reactive updated hook for real-time validation of new_email.
     */
    public function updatedNewEmail(): void
    {
        $this->validateOnly('new_email');
    }

    /**
     * Open modal listener.
     */
    #[On('openSubmitEmailChangeModal')]
    public function openModal(): void
    {
        $this->checkPendingRequest();
        $this->new_email = '';
        $this->new_email_confirmation = '';
        $this->justification = '';
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
     * Submit email change request for Workspace Manager security review.
     */
    public function submit(MemberChangeRequestService $service): void
    {
        $this->submissionError = null;
        $this->checkPendingRequest();

        if ($this->hasPendingRequest) {
            $this->submissionError = 'An email change request is already pending review for this member.';
            return;
        }

        $this->validate();

        try {
            $service->submitEmailChangeRequest($this->member, $this->new_email, $this->justification);

            $this->isOpen = false;
            $this->hasPendingRequest = true;
            $this->dispatch('email-change-submitted');
            session()->flash('status', 'Email change request submitted. Your Workspace Manager has been notified for security review.');
        } catch (DomainException $e) {
            $this->submissionError = $e->getMessage();
            $this->addError('new_email', $e->getMessage());
        }
    }

    /**
     * Render modal view.
     */
    public function render(): View
    {
        return view('livewire.portal.modals.submit-email-change-modal');
    }
}
