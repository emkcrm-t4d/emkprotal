<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class EditMemberContactModal extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Member $member;
    public bool $isOpen = false;

    // Editable non-identity fields
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

    /**
     * Mount component asserting tenancy context.
     */
    public function mount(string $workspace, Member $member): void
    {
        $this->workspace = $workspace;
        $this->member = $member;

        $this->syncFromMember();
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

    /**
     * Populate editable properties from member model.
     */
    protected function syncFromMember(): void
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
    }

    /**
     * Assert authorization for editing member contact and background details.
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Tenancy isolation check
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Permission check: members.edit required
        $canEdit = $user->can('members.edit')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canEdit) {
            App::abort(403, 'Unauthorized: members.edit permission required.');
        }
    }

    /**
     * Open modal listener.
     */
    #[On('openEditMemberContactModal')]
    public function openModal(): void
    {
        $this->authorizeAccess();
        $this->syncFromMember();
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
    }

    /**
     * Save updated contact and background information with immutable audit trail.
     */
    public function save(): void
    {
        $this->authorizeAccess();

        $this->validate([
            'contact_number' => ['required', 'string', 'regex:/^\+8801[3-9]\d{8}$/'],
            'address_house_no' => ['required', 'string', 'min:1', 'max:100'],
            'address_road_no' => ['nullable', 'string', 'max:100'],
            'address_area' => ['required', 'string', 'min:2', 'max:100'],
            'address_zip_code' => ['required', 'string', 'min:3', 'max:10'],
            'address_division' => ['required', 'string', 'in:barishal,chattogram,dhaka,khulna,mymensingh,rajshahi,rangpur,sylhet'],
            'address_district' => ['required', 'string', 'max:50'],
            'occupation' => ['required', 'string', 'in:artist,community_worker,educator,entrepreneur,media_professional,performer,student,other'],
            'institution_study' => ['required', 'string', 'min:2', 'max:150'],
            'highest_certification' => ['required', 'string', 'in:ssc,o_level,hsc,a_level,undergraduate_degree,undergraduate_honors,graduate,postgraduate,other'],
            'area_of_subject' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        $oldValues = [
            'contact_number' => $this->member->contact_number,
            'address_house_no' => $this->member->address_house_no,
            'address_road_no' => $this->member->address_road_no,
            'address_area' => $this->member->address_area,
            'address_zip_code' => $this->member->address_zip_code,
            'address_division' => $this->member->address_division,
            'address_district' => $this->member->address_district,
            'occupation' => $this->member->occupation,
            'institution_study' => $this->member->institution_study,
            'highest_certification' => $this->member->highest_certification,
            'area_of_subject' => $this->member->area_of_subject,
        ];

        $newValues = [
            'contact_number' => $this->contact_number,
            'address_house_no' => $this->address_house_no,
            'address_road_no' => $this->address_road_no ?: null,
            'address_area' => $this->address_area,
            'address_zip_code' => $this->address_zip_code,
            'address_division' => $this->address_division,
            'address_district' => $this->address_district,
            'occupation' => $this->occupation,
            'institution_study' => $this->institution_study,
            'highest_certification' => $this->highest_certification,
            'area_of_subject' => $this->area_of_subject,
        ];

        // Core identity attributes (first_name, middle_name, last_name, date_of_birth, sex, email, member_id)
        // are intentionally omitted and strictly immutable per T-07-02
        $this->member->update($newValues);

        $user = Auth::user();

        // Record immutable audit log entry per DEC-11
        DB::table('audit_logs')->insert([
            'workspace_id' => $this->member->workspace_id ?? $this->workspace,
            'actor_id' => $user->id,
            'actor_guard' => $user->guard ?? 'web',
            'event_type' => 'member_contact_updated',
            'auditable_type' => Member::class,
            'auditable_id' => $this->member->id,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'reason' => 'Staff updated member contact and address details.',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);

        $this->isOpen = false;
        $this->dispatch('member-contact-updated');
        session()->flash('status', 'Member contact and address details successfully updated.');
    }

    /**
     * Render modal view.
     */
    public function render(): View
    {
        return view('livewire.members.edit-member-contact-modal');
    }
}
