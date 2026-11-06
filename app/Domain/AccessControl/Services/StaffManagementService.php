<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Services;

use App\Domain\AccessControl\Exceptions\TenancyBoundaryException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class StaffManagementService
{
    public function __construct(
        protected DelegationPolicyService $delegationPolicy,
        protected ?PermissionRegistrar $permissionRegistrar = null
    ) {
        $this->permissionRegistrar ??= \Illuminate\Support\Facades\App::make(PermissionRegistrar::class);
    }

    /**
     * Create a new delegated staff user within the creator's workspace boundary.
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    public function createStaffUser(array $data, User $creator): User
    {
        $email = $data['email'] ?? null;
        $name = $data['name'] ?? null;

        if (empty($email) || empty($name)) {
            throw new InvalidArgumentException('Staff name and email are required.');
        }

        if (User::where('email', $email)->exists()) {
            throw new InvalidArgumentException("User with email [{$email}] already exists.");
        }

        $isGlobalAdmin = false;
        if (method_exists($creator, 'isSuperAdmin') && method_exists($creator, 'isGlobalAdmin')) {
            try {
                $isGlobalAdmin = $creator->isSuperAdmin() || $creator->isGlobalAdmin();
            } catch (\Throwable) {
                $isGlobalAdmin = false;
            }
        }

        if ($isGlobalAdmin) {
            $workspaceId = $data['workspace_id'] ?? $creator->workspace_id;
        } else {
            $workspaceId = $creator->workspace_id;
        }

        if (empty($workspaceId)) {
            throw new InvalidArgumentException('A valid workspace ID is required to create a staff member.');
        }

        $password = !empty($data['password'])
            ? Hash::make((string) $data['password'])
            : Hash::make(Str::random(16));

        $staffUser = User::create([
            'name' => (string) $name,
            'email' => (string) $email,
            'password' => $password,
            'workspace_id' => (string) $workspaceId,
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => true,
        ]);

        $staffUser->assignRole('delegated_staff');

        $this->permissionRegistrar->forgetCachedPermissions();

        // Dispatch admin alert on new staff user creation
        try {
            $emailDispatcher = app(\App\Domain\Mail\Services\QueuedEmailDispatcher::class);
            $adminEmail = $creator->email ?: 'admin@emk.test';

            $emailDispatcher->dispatchTemplate(
                workspaceId: (string) $workspaceId,
                templateKey: \App\Domain\Mail\Models\EmailTemplate::MILESTONE_ADMIN_NEW_USER,
                recipientEmail: $adminEmail,
                variables: [
                    'applicant_name' => $staffUser->name . ' (Staff Account: ' . $staffUser->email . ')',
                    'workspace_name' => 'EMK Center Dhaka',
                    'amount_bdt' => '0.00',
                    'action_url' => url('/crm/' . $workspaceId . '/settings/staff'),
                ],
                locale: 'en'
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Admin notification dispatch for staff user creation skipped: ' . $e->getMessage());
        }

        return $staffUser;
    }

    /**
     * Delegate an operational permission to a staff user.
     *
     * @throws \App\Domain\AccessControl\Exceptions\TenancyBoundaryException
     * @throws \App\Domain\AccessControl\Exceptions\UnauthorizedDelegationException
     */
    public function delegatePermission(User $manager, User $targetStaff, string $permission): void
    {
        $this->delegationPolicy->assertCanDelegate($manager, $permission, $targetStaff);

        $targetStaff->givePermissionTo($permission);

        $this->permissionRegistrar->forgetCachedPermissions();

        event(new \App\Domain\AccessControl\Events\StaffPermissionDelegated($manager, $targetStaff, $permission));
    }

    /**
     * Revoke a delegated permission from a staff user.
     *
     * @throws TenancyBoundaryException
     */
    public function revokeDelegatedPermission(User $manager, User $targetStaff, string $permission): void
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
            throw new TenancyBoundaryException('Cannot revoke permissions across workspace boundaries.');
        }

        $targetStaff->revokePermissionTo($permission);

        $this->permissionRegistrar->forgetCachedPermissions();

        event(new \App\Domain\AccessControl\Events\StaffPermissionRevoked($manager, $targetStaff, $permission));
    }

    /**
     * Deactivate a staff user, revoking access and invalidating cached permissions.
     *
     * @throws TenancyBoundaryException
     */
    public function deactivateStaffUser(User $actor, User $targetStaff): void
    {
        $isGlobalAdmin = false;
        if (method_exists($actor, 'isSuperAdmin') && method_exists($actor, 'isGlobalAdmin')) {
            try {
                $isGlobalAdmin = $actor->isSuperAdmin() || $actor->isGlobalAdmin();
            } catch (\Throwable) {
                $isGlobalAdmin = false;
            }
        }

        if (!$isGlobalAdmin && ($actor->workspace_id === null || $actor->workspace_id !== $targetStaff->workspace_id)) {
            throw new TenancyBoundaryException('Cannot deactivate staff across workspace boundaries.');
        }

        $targetStaff->is_active = false;
        $targetStaff->save();

        $this->permissionRegistrar->forgetCachedPermissions();
    }

    /**
     * Activate a staff user.
     *
     * @throws TenancyBoundaryException
     */
    public function activateStaffUser(User $actor, User $targetStaff): void
    {
        $isGlobalAdmin = false;
        if (method_exists($actor, 'isSuperAdmin') && method_exists($actor, 'isGlobalAdmin')) {
            try {
                $isGlobalAdmin = $actor->isSuperAdmin() || $actor->isGlobalAdmin();
            } catch (\Throwable) {
                $isGlobalAdmin = false;
            }
        }

        if (!$isGlobalAdmin && ($actor->workspace_id === null || $actor->workspace_id !== $targetStaff->workspace_id)) {
            throw new TenancyBoundaryException('Cannot activate staff across workspace boundaries.');
        }

        $targetStaff->is_active = true;
        $targetStaff->save();

        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
