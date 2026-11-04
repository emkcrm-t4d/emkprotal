<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Services;

use App\Domain\AccessControl\Exceptions\TenancyBoundaryException;
use App\Domain\AccessControl\Exceptions\UnauthorizedDelegationException;
use App\Domain\Platform\Registry\ModuleRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DelegationPolicyService
{
    /**
     * Strictly delegable actions that a Workspace Manager may grant to Staff.
     * All unlisted actions are non-delegable by default (Fail Closed).
     *
     * @var list<string>
     */
    public const DELEGABLE_ACTIONS = ModuleRegistry::DELEGABLE_ACTIONS;

    /**
     * Actions explicitly reserved for Workspace Manager or above.
     * Hardcoded restriction preventing manager privilege elevation per T-01-07.
     *
     * @var list<string>
     */
    public const STRICTLY_NON_DELEGABLE = ModuleRegistry::STRICTLY_NON_DELEGABLE;

    /**
     * Assert that the given manager has authority to delegate the permission to the target staff member.
     *
     * @throws TenancyBoundaryException If manager and staff belong to different workspaces
     * @throws UnauthorizedDelegationException If permission is not in the delegable allowlist
     */
    public function assertCanDelegate(User $manager, string $permission, User $targetStaff): void
    {
        $isGlobalAdmin = false;
        if (method_exists($manager, 'isSuperAdmin') && method_exists($manager, 'isGlobalAdmin')) {
            try {
                $isGlobalAdmin = $manager->isSuperAdmin() || $manager->isGlobalAdmin();
            } catch (\Throwable) {
                $isGlobalAdmin = false;
            }
        }

        if (!$isGlobalAdmin && ($manager->workspace_id === null || $manager->workspace_id !== $targetStaff->workspace_id)) {
            throw new TenancyBoundaryException('Cannot delegate permissions across workspace boundaries.');
        }

        if (!$this->isDelegable($permission)) {
            try {
                Log::warning("Security Alert: Attempted unauthorized delegation of {$permission} by Manager {$manager->id}");
            } catch (\Throwable) {
                // Facade root may not be bound in isolated unit test environments
            }

            throw new UnauthorizedDelegationException("Action {$permission} is non-delegable to staff.");
        }
    }

    /**
     * Determine if an action is delegable by Workspace Managers to Staff.
     */
    public function isDelegable(string $permission): bool
    {
        return in_array($permission, self::DELEGABLE_ACTIONS, true);
    }

    /**
     * Determine if an action is strictly non-delegable managerial action.
     */
    public function isStrictlyNonDelegable(string $permission): bool
    {
        return in_array($permission, self::STRICTLY_NON_DELEGABLE, true);
    }

    /**
     * Get all delegable actions.
     *
     * @return list<string>
     */
    public function getDelegableActions(): array
    {
        return self::DELEGABLE_ACTIONS;
    }

    /**
     * Get all strictly non-delegable actions.
     *
     * @return list<string>
     */
    public function getStrictlyNonDelegableActions(): array
    {
        return self::STRICTLY_NON_DELEGABLE;
    }
}
