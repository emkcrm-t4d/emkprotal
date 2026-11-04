<?php

declare(strict_types=1);

namespace App\Livewire\Leads;

use App\Domain\Leads\Models\Lead;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.admin')]
class LeadProfile extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Lead $lead;

    // Tab state: 'details', 'attachments', 'streams', 'timeline'
    public string $activeTab = 'details';

    // Reapplication detection per LEAD-07
    public bool $hasReapplicationHistory = false;
    public array $historicalLeads = [];

    // Inline edit mode state per LEAD-01
    public bool $editMode = false;

    // Editable contact and address fields (leads.edit permission required)
    public string $contact_number = '';
    public string $address_house_no = '';
    public string $address_road_no = '';
    public string $address_area = '';
    public string $address_zip_code = '';
    public string $address_division = '';
    public string $address_district = '';
    public string $occupation = '';
    public string $occupation_other = '';
    public string $institution_study = '';

    /**
     * Mount component asserting authentication, tenancy isolation, and Spatie permission.
     */
    public function mount(string $workspace, Lead|int|string $lead): void
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

        // Resolve model instance
        if (!($lead instanceof Lead)) {
            $lead = Lead::findOrFail($lead);
        }

        // Layer 2 / Tenancy isolation assertion: Reject cross-workspace entity
        if (!$isElevatedAdmin && (string) $lead->workspace_id !== (string) $user->workspace_id) {
            App::abort(403, 'Unauthorized workspace entity.');
        }

        if ((string) $lead->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Lead does not belong to the requested workspace.');
        }

        // Eager load attachments and verifications
        $lead->loadMissing(['attachments', 'verifications.verifiedBy']);

        $this->lead = $lead;

        $this->syncEditableProperties();
        $this->checkReapplicationHistory();
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
     * Populate editable properties from lead model.
     */
    protected function syncEditableProperties(): void
    {
        $this->contact_number = (string) $this->lead->contact_number;
        $this->address_house_no = (string) $this->lead->address_house_no;
        $this->address_road_no = (string) ($this->lead->address_road_no ?? '');
        $this->address_area = (string) $this->lead->address_area;
        $this->address_zip_code = (string) $this->lead->address_zip_code;
        $this->address_division = (string) $this->lead->address_division;
        $this->address_district = (string) $this->lead->address_district;
        $this->occupation = (string) $this->lead->occupation;
        $this->occupation_other = (string) ($this->lead->occupation_other ?? '');
        $this->institution_study = (string) $this->lead->institution_study;
    }

    /**
     * Check for prior submissions matching email per LEAD-07.
     */
    protected function checkReapplicationHistory(): void
    {
        // Query historical submissions excluding current record
        $history = Lead::with('verifications')
            ->where('email', $this->lead->email)
            ->where('id', '!=', $this->lead->id)
            ->orderByDesc('created_at')
            ->get();

        $this->hasReapplicationHistory = $history->isNotEmpty();
        $this->historicalLeads = $history->toArray();
    }

    /**
     * Switch active profile tab.
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * Refresh lead entity upon verification or lifecycle mutations.
     */
    #[On('leadUpdated')]
    #[On('lead-updated')]
    public function refreshLead(): void
    {
        $this->lead->refresh();
        $this->lead->loadMissing(['attachments', 'verifications.verifiedBy']);
        $this->syncEditableProperties();
        $this->checkReapplicationHistory();
    }

    /**
     * Open missing items modal.
     */
    public function openMissingItemsModal(): void
    {
        $this->dispatch('openMissingItemsModal');
    }

    /**
     * Open standardized rejection modal.
     */
    public function openRejectModal(): void
    {
        $this->dispatch('openRejectLeadModal');
    }

    /**
     * Toggle inline edit mode for non-identity contact/address fields.
     */
    public function toggleEditMode(): void
    {
        $user = Auth::user();
        $canEdit = $user && ($user->can('leads.edit') || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager'])));

        if (!$canEdit) {
            App::abort(403, 'Unauthorized: leads.edit permission required.');
        }

        $this->editMode = !$this->editMode;

        if (!$this->editMode) {
            $this->syncEditableProperties();
            $this->resetValidation();
        }
    }

    /**
     * Save editable contact details and log immutable audit event.
     */
    public function saveContactDetails(): void
    {
        $user = Auth::user();
        $canEdit = $user && ($user->can('leads.edit') || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager'])));

        if (!$canEdit) {
            App::abort(403, 'Unauthorized: leads.edit permission required.');
        }

        $this->validate([
            'contact_number' => ['required', 'string', 'regex:/^\+8801[3-9]\d{8}$/'],
            'address_house_no' => ['required', 'string', 'min:1', 'max:100'],
            'address_road_no' => ['nullable', 'string', 'max:100'],
            'address_area' => ['required', 'string', 'min:2', 'max:100'],
            'address_zip_code' => ['required', 'string', 'min:3', 'max:10'],
            'address_division' => ['required', 'string', 'in:barishal,chattogram,dhaka,khulna,mymensingh,rajshahi,rangpur,sylhet'],
            'address_district' => ['required', 'string', 'max:50'],
            'occupation' => ['required', 'string', 'in:artist,community_worker,educator,entrepreneur,media_professional,performer,student,other'],
            'occupation_other' => ['nullable', 'string', 'max:100'],
            'institution_study' => ['required', 'string', 'min:2', 'max:150'],
        ]);

        $oldValues = [
            'contact_number' => $this->lead->contact_number,
            'address_house_no' => $this->lead->address_house_no,
            'address_road_no' => $this->lead->address_road_no,
            'address_area' => $this->lead->address_area,
            'address_zip_code' => $this->lead->address_zip_code,
            'address_division' => $this->lead->address_division,
            'address_district' => $this->lead->address_district,
            'occupation' => $this->lead->occupation,
            'occupation_other' => $this->lead->occupation_other,
            'institution_study' => $this->lead->institution_study,
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
            'occupation_other' => $this->occupation_other ?: null,
            'institution_study' => $this->institution_study,
        ];

        // Update Lead model (identity fields remain strictly untouched per T-06-02)
        $this->lead->update($newValues);

        // Record immutable audit event per DEC-11
        DB::table('audit_logs')->insert([
            'workspace_id' => $this->lead->workspace_id ?? $this->workspace,
            'actor_id' => $user->id,
            'actor_guard' => $user->guard ?? 'web',
            'event_type' => 'lead_contact_updated',
            'auditable_type' => Lead::class,
            'auditable_id' => $this->lead->id,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'reason' => 'Staff updated applicant contact/address details.',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);

        $this->editMode = false;
        session()->flash('status', 'Lead contact and address details successfully updated.');
    }

    /**
     * Render the lead profile view.
     */
    public function render(): View
    {
        return view('livewire.leads.lead-profile', [
            'lead' => $this->lead,
            'activeModule' => 'leads',
        ])->layout('layouts.admin', [
            'activeModule' => 'leads',
            'title' => 'Lead Profile - ' . $this->lead->full_name,
        ]);
    }
}
