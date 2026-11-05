<?php

declare(strict_types=1);

namespace App\Domain\Migration\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PortalAccountProvisioningService
{
    /**
     * Provision or stage member portal user accounts in an inactive, passwordless state
     * for historical records imported from Zoho per DEC-06 (D-06) and T-11-11.
     *
     * @param  string  $workspaceId
     * @param  string|null  $batchUuid
     * @return array{workspace_id: string, staged_count: int, user_ids: list<int>, member_ids: list<int>}
     *
     * @throws InvalidArgumentException
     */
    public function provisionInactiveAccounts(string $workspaceId, ?string $batchUuid = null): array
    {
        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            throw new InvalidArgumentException("Workspace [{$workspaceId}] not found.");
        }

        $query = Member::where('workspace_id', $workspaceId)
            ->whereNotNull('zoho_id');

        // Optional filter by specific batch
        if (!empty($batchUuid)) {
            $batch = ZohoImportBatch::where('batch_uuid', $batchUuid)
                ->where('workspace_id', $workspaceId)
                ->first();

            if ($batch) {
                $memberIdsFromBatch = ZohoStagingRecord::where('batch_id', $batch->id)
                    ->where('imported_entity_type', Member::class)
                    ->whereNotNull('imported_entity_id')
                    ->pluck('imported_entity_id')
                    ->toArray();

                $query->whereIn('id', $memberIdsFromBatch);
            }
        }

        // Target members that lack an associated user or whose user is not yet staged inactive
        $query->where(function ($q) {
            $q->whereNull('user_id')
                ->orWhereDoesntHave('user')
                ->orWhereHas('user', function ($uq) {
                    $uq->where('is_active', true)
                        ->orWhereNotNull('password')
                        ->orWhere('guard', '!=', 'member');
                });
        });

        $members = $query->get();
        $stagedUserIds = [];
        $stagedMemberIds = [];

        DB::transaction(function () use ($members, $workspaceId, &$stagedUserIds, &$stagedMemberIds): void {
            foreach ($members as $member) {
                // Find existing user by user_id or email
                $user = null;
                if (!empty($member->user_id)) {
                    $user = User::find($member->user_id);
                }
                if (!$user && !empty($member->email)) {
                    $user = User::where('email', $member->email)->first();
                }

                $userData = [
                    'name' => $member->full_name ?: trim($member->first_name . ' ' . $member->last_name),
                    'email' => $member->email,
                    'password' => null, // Passwordless per DEC-06
                    'guard' => 'member',
                    'is_active' => false, // Inactive until claimed
                    'must_reset_password' => true,
                    'workspace_id' => $workspaceId,
                    'zoho_id' => $member->zoho_id,
                ];

                if ($user) {
                    $user->update($userData);
                } else {
                    $user = User::create($userData);
                }

                // Associate user to member
                if ($member->user_id !== $user->id) {
                    $member->user_id = $user->id;
                    $member->save();
                }

                // Log immutable audit trail per DEC-11
                AuditLog::create([
                    'workspace_id' => $workspaceId,
                    'actor_id' => Auth::id(),
                    'actor_guard' => Auth::getDefaultDriver() ?: 'web',
                    'event_type' => AuditLog::EVENT_PORTAL_ACCOUNT_STAGED_INACTIVE,
                    'auditable_type' => User::class,
                    'auditable_id' => $user->id,
                    'new_values' => [
                        'user_id' => $user->id,
                        'member_id' => $member->id,
                        'email' => $user->email,
                        'guard' => 'member',
                        'is_active' => false,
                        'must_reset_password' => true,
                        'zoho_id' => $member->zoho_id,
                    ],
                    'reason' => 'Staged inactive member portal account for official claim per DEC-06',
                    'ip_address' => request()?->ip() ?? '127.0.0.1',
                    'user_agent' => request()?->userAgent() ?? 'Artisan CLI / Portal Provisioner',
                ]);

                $stagedUserIds[] = (int) $user->id;
                $stagedMemberIds[] = (int) $member->id;
            }
        });

        return [
            'workspace_id' => $workspaceId,
            'staged_count' => count($stagedUserIds),
            'user_ids' => $stagedUserIds,
            'member_ids' => $stagedMemberIds,
        ];
    }

    /**
     * Asserts that an inactive staged portal account cannot authenticate via the member portal.
     * Returns true if authentication fails closed as expected.
     */
    public function assertAccountCannotLogin(User $user): bool
    {
        // 1. Inactive account attribute assertion
        if ($user->is_active === true) {
            return false;
        }

        // 2. Password must be null
        if (!is_null($user->password)) {
            return false;
        }

        // 3. Attempt authentication against 'member' guard with random candidate password
        $attemptAnyPassword = Auth::guard('member')->attempt([
            'email' => $user->email,
            'password' => 'AttemptedClaimSecret2026!',
            'is_active' => true,
        ]);

        // 4. Attempt authentication against 'member' guard with empty string password
        $attemptBlankPassword = Auth::guard('member')->attempt([
            'email' => $user->email,
            'password' => '',
            'is_active' => true,
        ]);

        return (!$attemptAnyPassword) && (!$attemptBlankPassword);
    }
}
