<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Modals;

use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\MemberChangeRequestService;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class SubmitProfileChangeRequestModal extends Component
{
    public Member $member;
    public bool $isOpen = false;
    public bool $hasPendingRequest = false;
    public ?string $submissionError = null;

    // Editable non-identity contact and address fields per PORT-03 / DEC-06
    public string $contact_number = '';
    public string $address_house_no = '';
    public string $address_road_no = '';
    public string $address_area = '';
    public string $address_zip_code = '';
    public string $address_division = '';
    public string $address_district = '';
    public string $occupation = '';
    public string $institution_study = '';
    public string $highest_certification = '';
    public string $area_of_subject = '';
    public string $justification = '';

    /**
     * Mount component asserting member entity.
     */
    public function mount(Member $member): void
    {
        $this->member = $member;
        $this->checkPendingRequest();
        $this->syncFromMember();
    }

    /**
     * Check if a general update request is already pending.
     */
    public function checkPendingRequest(): void
    {
        $this->hasPendingRequest = MemberChangeRequest::withoutGlobalScopes()
            ->where('member_id', $this->member->id)
            ->where('request_type', MemberChangeRequest::TYPE_GENERAL_UPDATE)
            ->where('status', MemberChangeRequest::STATUS_PENDING)
            ->exists();
    }

    /**
     * Sync state from member model.
     */
    public function syncFromMember(): void
    {
        $this->contact_number = (string) $this->member->contact_number;
        $this->address_house_no = (string) $this->member->address_house_no;
        $this->address_road_no = (string) ($this->member->address_road_no ?? '');
        $this->address_area = (string) $this->member->address_area;
        $this->address_zip_code = (string) $this->member->address_zip_code;
        $this->address_division = (string) $this->member->address_division;
        $this->address_district = (string) $this->member->address_district;
        $this->occupation = (string) $this->member->occupation;
        $this->institution_study = (string) $this->member->institution_study;
        $this->highest_certification = (string) $this->member->highest_certification;
        $this->area_of_subject = (string) $this->member->area_of_subject;
        $this->justification = '';
    }

    /**
     * Open modal listener.
     */
    #[On('openSubmitProfileChangeRequestModal')]
    public function openModal(): void
    {
        $this->checkPendingRequest();
        $this->syncFromMember();
        $this->resetValidation();
        $this->submissionError = null;
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
     * Submit profile change request for administrative review.
     */
    public function submit(MemberChangeRequestService $service): void
    {
        $this->submissionError = null;
        $this->checkPendingRequest();

        if ($this->hasPendingRequest) {
            $this->submissionError = 'A profile change request is already pending review for this member.';
            return;
        }

        $this->validate([
            'contact_number' => ['required', 'string', 'max:20'],
            'address_house_no' => ['required', 'string', 'max:100'],
            'address_road_no' => ['nullable', 'string', 'max:100'],
            'address_area' => ['required', 'string', 'max:100'],
            'address_zip_code' => ['required', 'string', 'max:10'],
            'address_division' => ['required', 'string', 'max:50'],
            'address_district' => ['required', 'string', 'max:50'],
            'occupation' => ['required', 'string', 'max:50'],
            'institution_study' => ['required', 'string', 'max:150'],
            'highest_certification' => ['required', 'string', 'max:50'],
            'area_of_subject' => ['required', 'string', 'max:150'],
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $payload = [
            'contact_number' => $this->contact_number,
            'address_house_no' => $this->address_house_no,
            'address_road_no' => $this->address_road_no !== '' ? $this->address_road_no : null,
            'address_area' => $this->address_area,
            'address_zip_code' => $this->address_zip_code,
            'address_division' => $this->address_division,
            'address_district' => $this->address_district,
            'occupation' => $this->occupation,
            'institution_study' => $this->institution_study,
            'highest_certification' => $this->highest_certification,
            'area_of_subject' => $this->area_of_subject,
        ];

        try {
            $service->submitGeneralUpdateRequest($this->member, $payload, $this->justification);

            $this->isOpen = false;
            $this->hasPendingRequest = true;
            $this->dispatch('change-request-submitted');
            session()->flash('status', 'Your change request has been submitted to workspace staff for review.');
        } catch (DomainException $e) {
            $this->submissionError = $e->getMessage();
            $this->addError('justification', $e->getMessage());
        }
    }

    /**
     * Render modal view.
     */
    public function render(): View
    {
        return view('livewire.portal.modals.submit-profile-change-request-modal');
    }
}
