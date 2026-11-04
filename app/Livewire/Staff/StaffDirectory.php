<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Domain\AccessControl\Services\StaffManagementService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class StaffDirectory extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public ?int $selectedStaffId = null;
    public bool $showDelegationModal = false;
    public ?string $statusMessage = null;
    public ?string $errorMessage = null;

    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        if (!($user->hasRole(['superadmin', 'global_admin'])) && $user->workspace_id !== $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        if (!$user->can('platform_services.delegate_roles') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin'])) {
            App::abort(403, 'Forbidden: Insufficient privileges to view staff directory.');
        }
    }

    public function openDelegation(int $staffId): void
    {
        $this->selectedStaffId = $staffId;
        $this->showDelegationModal = true;
        $this->errorMessage = null;
    }

    public function closeDelegation(): void
    {
        $this->selectedStaffId = null;
        $this->showDelegationModal = false;
        $this->errorMessage = null;
    }

    public function toggleActivation(int $staffId, StaffManagementService $staffService): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $targetStaff = User::where('workspace_id', $this->workspace)->findOrFail($staffId);

        try {
            if ($targetStaff->is_active) {
                $staffService->deactivateStaffUser($user, $targetStaff);
                $this->statusMessage = "Staff account for {$targetStaff->name} has been deactivated.";
            } else {
                $staffService->activateStaffUser($user, $targetStaff);
                $this->statusMessage = "Staff account for {$targetStaff->name} has been activated.";
            }
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->statusMessage = null;
        }
    }

    public function render(): View
    {
        $staffUsers = User::where('workspace_id', $this->workspace)
            ->with(['roles', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('livewire.staff.staff-directory', [
            'staffUsers' => $staffUsers,
            'workspace' => $this->workspace,
        ]);
    }
}
