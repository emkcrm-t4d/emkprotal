<?php

declare(strict_types=1);

namespace App\Domain\Members\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Members\Models\Member;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Governs administrative member status transitions per STATUS-DICTIONARY Section 6 / MEMB-03.
 */
class MemberStatusService
{
    public const MIN_RATIONALE_LENGTH = 20;

    /**
     * Allowed status transitions: current => [targets].
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'active' => ['inactive', 'restricted', 'revoked'],
        'inactive' => ['restricted', 'revoked'],
        'restricted' => ['active', 'revoked'],
        'revoked' => [],
    ];

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws LogicException
     */
    public function transitionStatus(
        Member $member,
        string $targetStatus,
        string $directiveRef,
        string $rationale,
        User $actor
    ): Member {
        $currentStatus = (string) $member->status;

        if (!in_array($targetStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw new LogicException("Invalid member status transition from [{$currentStatus}] to [{$targetStatus}].");
        }

        $this->assertAuthority($targetStatus, $actor);

        $directiveRef = trim($directiveRef);
        $rationale = trim($rationale);

        if (in_array($targetStatus, ['restricted', 'revoked'], true)) {
            $errors = [];
            if ($directiveRef === '') {
                $errors['directiveRef'] = 'A management directive reference is required.';
            }
            if (mb_strlen($rationale) < self::MIN_RATIONALE_LENGTH) {
                $errors['rationale'] = 'Rationale must be at least ' . self::MIN_RATIONALE_LENGTH . ' characters.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        return DB::transaction(function () use ($member, $currentStatus, $targetStatus, $directiveRef, $rationale, $actor): Member {
            $member->status = $targetStatus;
            $member->save();

            if ($targetStatus === 'revoked' && $member->user_id) {
                $portalUser = User::find($member->user_id);
                if ($portalUser) {
                    $portalUser->is_active = false;
                    $portalUser->remember_token = null;
                    $portalUser->save();

                    // Invalidate any active sessions (database session driver)
                    if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                        DB::table('sessions')->where('user_id', $portalUser->id)->delete();
                    }
                }
            }

            AuditLog::create([
                'workspace_id' => $member->workspace_id,
                'actor_id' => $actor->id,
                'actor_guard' => 'web',
                'event_type' => 'member_status_changed',
                'auditable_type' => Member::class,
                'auditable_id' => $member->id,
                'old_values' => ['status' => $currentStatus],
                'new_values' => [
                    'status' => $targetStatus,
                    'directive_ref' => $directiveRef,
                    'rationale' => $rationale,
                    'actor_user_id' => $actor->id,
                ],
                'reason' => $rationale !== '' ? $rationale : "Status changed to {$targetStatus}.",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/Console',
            ]);

            return $member;
        });
    }

    /**
     * @throws AuthorizationException
     */
    protected function assertAuthority(string $targetStatus, User $actor): void
    {
        if ($targetStatus === 'revoked') {
            $allowed = $actor->can('members.revoke') || $actor->hasRole('superadmin');
            if (!$allowed) {
                throw new AuthorizationException('Revoking membership requires members.revoke permission or superadmin role.');
            }

            return;
        }

        $allowed = $actor->can('members.restrict') || $actor->hasRole(['global_admin', 'superadmin']);
        if (!$allowed) {
            throw new AuthorizationException('Changing member status requires members.restrict permission or global admin authority.');
        }
    }
}
