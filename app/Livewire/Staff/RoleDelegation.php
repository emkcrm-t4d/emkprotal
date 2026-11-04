<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Domain\AccessControl\Exceptions\TenancyBoundaryException;
use App\Domain\AccessControl\Exceptions\UnauthorizedDelegationException;
use App\Domain\AccessControl\Services\DelegationPolicyService;
use App\Domain\AccessControl\Services\StaffManagementService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class RoleDelegation extends Component
{
    use ValidatesWorkspaceOnHydration;

    public int|string|null $staffId = null;
    public ?User $staff = null;
    public array $assignedPermissions = [];
    public ?string $statusMessage = null;
    public ?string $errorMessage = null;

    public function mount(
        string|null $workspace = null,
        int|string|User|null $staff = null,
        int|string|null $staffId = null
    ): void {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        if (!$user->can('platform_services.delegate_roles') && !$user->hasRole(['workspace_manager', 'superadmin', 'global_admin'])) {
            App::abort(403, 'Forbidden: Insufficient privileges for role delegation.');
        }

        if ($staff instanceof User) {
            $this->staff = $staff;
            $this->staffId = $staff->id;
        } elseif ($staff !== null) {
            $this->staff = User::findOrFail($staff);
            $this->staffId = $this->staff->id;
        } elseif ($staffId !== null) {
            $this->staff = User::findOrFail($staffId);
            $this->staffId = $this->staff->id;
        }

        if ($this->staff) {
            $isGlobalAdmin = $user->hasRole(['superadmin', 'global_admin']);
            if (!$isGlobalAdmin && $user->workspace_id !== $this->staff->workspace_id) {
                App::abort(403, 'Unauthorized access to staff in another workspace.');
            }

            $this->loadAssignedPermissions();
        }
    }

    public function loadAssignedPermissions(): void
    {
        if (!$this->staff) {
            $this->assignedPermissions = [];
            return;
        }

        // Load permissions granted directly to this staff user
        $this->assignedPermissions = $this->staff->getDirectPermissions()->pluck('name')->toArray();
    }

    public function togglePermission(string $permissionToken, StaffManagementService $staffService): void
    {
        $this->statusMessage = null;
        $this->errorMessage = null;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        if (!$this->staff) {
            $this->errorMessage = 'Target staff user not selected.';
            return;
        }

        try {
            if (in_array($permissionToken, $this->assignedPermissions, true)) {
                $staffService->revokeDelegatedPermission($user, $this->staff, $permissionToken);
                $this->statusMessage = "Permission [{$permissionToken}] revoked successfully.";
            } else {
                $staffService->delegatePermission($user, $this->staff, $permissionToken);
                $this->statusMessage = "Permission [{$permissionToken}] delegated successfully.";
            }

            $this->staff->refresh();
            $this->loadAssignedPermissions();
        } catch (UnauthorizedDelegationException|TenancyBoundaryException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to update permission: ' . $e->getMessage();
        }
    }

    public function render(DelegationPolicyService $delegationPolicy): View
    {
        return view('livewire.staff.role-delegation', [
            'staff' => $this->staff,
            'delegableActions' => $delegationPolicy->getDelegableActions(),
            'nonDelegableActions' => $delegationPolicy->getStrictlyNonDelegableActions(),
            'assignedPermissions' => $this->assignedPermissions,
        ]);
    }
}
