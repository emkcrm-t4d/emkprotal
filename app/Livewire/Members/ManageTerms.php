<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class ManageTerms extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public bool $showModal = false;
    public ?int $editingTermId = null;

    public string $name = '';
    public string $code = '';
    public string $durationMonths = '';
    public string $feeBdt = '';
    public bool $isActive = true;
    public bool $isLocked = false;

    public string $search = '';

    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;
        $this->authorizeAccess();
    }

    /**
     * Assert managerial authorization and workspace tenancy access.
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $canManage = $user->can('platform_services.manage_settings')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canManage) {
            App::abort(403, 'Unauthorized: platform_services.manage_settings permission required.');
        }

        if (!$user->hasRole(['superadmin', 'global_admin']) && (string) $this->workspace !== (string) $user->workspace_id) {
            App::abort(403, 'Unauthorized workspace access.');
        }
    }

    /**
     * Open modal dialog to create a new membership term.
     */
    public function openCreateModal(): void
    {
        $this->authorizeAccess();
        $this->resetValidation();

        $this->editingTermId = null;
        $this->name = '';
        $this->code = '';
        $this->durationMonths = '';
        $this->feeBdt = '';
        $this->isActive = true;
        $this->isLocked = false;

        $this->showModal = true;
    }

    /**
     * Open modal dialog to edit an existing membership term.
     */
    public function editTerm(int $termId): void
    {
        $this->authorizeAccess();
        $this->resetValidation();

        $term = MembershipTerm::where('workspace_id', $this->workspace)->findOrFail($termId);

        $this->editingTermId = $term->id;
        $this->name = $term->name;
        $this->code = $term->code;
        $this->durationMonths = (string) $term->duration_months;
        $this->feeBdt = (string) $term->fee_bdt;
        $this->isActive = (bool) $term->is_active;
        $this->isLocked = $term->members()->exists();

        $this->showModal = true;
    }

    /**
     * Close modal dialog.
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    /**
     * Create a new membership term for the active workspace.
     */
    public function createTerm(): void
    {
        $this->authorizeAccess();

        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('membership_terms', 'code')->where('workspace_id', $this->workspace),
            ],
            'durationMonths' => ['required', 'integer', 'min:1', 'max:60'],
            'feeBdt' => ['required', 'numeric', 'min:0'],
            'isActive' => ['boolean'],
        ], [
            'code.regex' => 'The term code must contain only lowercase alphanumeric characters and underscores.',
        ]);

        $term = MembershipTerm::create([
            'workspace_id' => $this->workspace,
            'name' => trim($this->name),
            'code' => trim($this->code),
            'duration_months' => (int) $this->durationMonths,
            'fee_bdt' => (float) $this->feeBdt,
            'is_active' => (bool) $this->isActive,
        ]);

        $user = Auth::user();

        // Emit immutable audit log entry per DEC-11
        DB::table('audit_logs')->insert([
            'workspace_id' => $this->workspace,
            'actor_id' => $user->id,
            'actor_guard' => $user->guard ?? 'web',
            'event_type' => AuditLog::EVENT_MEMBERSHIP_TERM_CREATED,
            'auditable_type' => MembershipTerm::class,
            'auditable_id' => $term->id,
            'old_values' => json_encode([]),
            'new_values' => json_encode([
                'name' => $term->name,
                'code' => $term->code,
                'duration_months' => $term->duration_months,
                'fee_bdt' => (string) $term->fee_bdt,
                'is_active' => (bool) $term->is_active,
            ]),
            'reason' => 'Workspace manager created membership term.',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);

        $this->showModal = false;
        session()->flash('status', 'Membership term created successfully.');
    }

    /**
     * Update an existing membership term.
     */
    public function updateTerm(): void
    {
        $this->authorizeAccess();

        if (!$this->editingTermId) {
            $this->createTerm();
            return;
        }

        $term = MembershipTerm::where('workspace_id', $this->workspace)->findOrFail($this->editingTermId);
        $this->isLocked = $term->members()->exists();

        if ($this->isLocked) {
            // Tampering defense: prevent alteration of code or duration when members are assigned
            if ((int) $this->durationMonths !== (int) $term->duration_months) {
                $this->addError('durationMonths', 'Term duration cannot be modified once members are assigned.');
                return;
            }

            if ($this->code !== $term->code) {
                $this->addError('code', 'Term code cannot be modified once members are assigned.');
                return;
            }

            $this->validate([
                'name' => ['required', 'string', 'max:100'],
                'feeBdt' => ['required', 'numeric', 'min:0'],
                'isActive' => ['boolean'],
            ]);

            $oldValues = [
                'name' => $term->name,
                'fee_bdt' => (string) $term->fee_bdt,
                'is_active' => (bool) $term->is_active,
            ];

            $term->update([
                'name' => trim($this->name),
                'fee_bdt' => (float) $this->feeBdt,
                'is_active' => (bool) $this->isActive,
            ]);

            $newValues = [
                'name' => $term->name,
                'fee_bdt' => (string) $term->fee_bdt,
                'is_active' => (bool) $term->is_active,
            ];
        } else {
            $this->validate([
                'name' => ['required', 'string', 'max:100'],
                'code' => [
                    'required',
                    'string',
                    'max:50',
                    'regex:/^[a-z0-9_]+$/',
                    Rule::unique('membership_terms', 'code')->where('workspace_id', $this->workspace)->ignore($term->id),
                ],
                'durationMonths' => ['required', 'integer', 'min:1', 'max:60'],
                'feeBdt' => ['required', 'numeric', 'min:0'],
                'isActive' => ['boolean'],
            ], [
                'code.regex' => 'The term code must contain only lowercase alphanumeric characters and underscores.',
            ]);

            $oldValues = [
                'name' => $term->name,
                'code' => $term->code,
                'duration_months' => $term->duration_months,
                'fee_bdt' => (string) $term->fee_bdt,
                'is_active' => (bool) $term->is_active,
            ];

            $term->update([
                'name' => trim($this->name),
                'code' => trim($this->code),
                'duration_months' => (int) $this->durationMonths,
                'fee_bdt' => (float) $this->feeBdt,
                'is_active' => (bool) $this->isActive,
            ]);

            $newValues = [
                'name' => $term->name,
                'code' => $term->code,
                'duration_months' => $term->duration_months,
                'fee_bdt' => (string) $term->fee_bdt,
                'is_active' => (bool) $term->is_active,
            ];
        }

        $user = Auth::user();

        // Emit immutable audit log entry per DEC-11
        DB::table('audit_logs')->insert([
            'workspace_id' => $this->workspace,
            'actor_id' => $user->id,
            'actor_guard' => $user->guard ?? 'web',
            'event_type' => AuditLog::EVENT_MEMBERSHIP_TERM_UPDATED,
            'auditable_type' => MembershipTerm::class,
            'auditable_id' => $term->id,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'reason' => 'Workspace manager updated membership term.',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);

        $this->showModal = false;
        session()->flash('status', 'Membership term updated successfully.');
    }

    /**
     * Unified save handler (supports modal submit).
     */
    public function saveTerm(): void
    {
        if ($this->editingTermId) {
            $this->updateTerm();
        } else {
            $this->createTerm();
        }
    }

    /**
     * Toggle active/inactive status for a term.
     */
    public function toggleActive(int $termId): void
    {
        $this->authorizeAccess();

        $term = MembershipTerm::where('workspace_id', $this->workspace)->findOrFail($termId);
        $oldActive = (bool) $term->is_active;
        $newActive = !$oldActive;

        $term->update([
            'is_active' => $newActive,
        ]);

        $user = Auth::user();

        // Emit immutable audit log entry per DEC-11
        DB::table('audit_logs')->insert([
            'workspace_id' => $this->workspace,
            'actor_id' => $user->id,
            'actor_guard' => $user->guard ?? 'web',
            'event_type' => AuditLog::EVENT_MEMBERSHIP_TERM_UPDATED,
            'auditable_type' => MembershipTerm::class,
            'auditable_id' => $term->id,
            'old_values' => json_encode(['is_active' => $oldActive]),
            'new_values' => json_encode(['is_active' => $newActive]),
            'reason' => 'Workspace manager toggled membership term active status.',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);

        session()->flash('status', 'Membership term status updated.');
    }

    public function render(): View
    {
        $terms = MembershipTerm::where('workspace_id', $this->workspace)
            ->withCount('members')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.members.manage-terms', [
            'terms' => $terms,
        ]);
    }
}
