<?php

declare(strict_types=1);

namespace App\Domain\Members\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\WorkflowRule;
use App\Domain\Mail\Services\WorkflowRuleEngine;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Mail\ManagerEmailChangeAlertMail;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MemberChangeRequestService
{
    public const PERMITTED_GENERAL_FIELDS = [
        'contact_number',
        'address_house_no',
        'address_road_no',
        'address_area',
        'address_zip_code',
        'address_division',
        'address_district',
        'occupation',
        'institution_study',
        'highest_certification',
        'area_of_subject',
    ];

    public function __construct(
        protected ?WorkflowRuleEngine $workflowEngine = null
    ) {
    }

    /**
     * Submit a general profile update change request for a member.
     * Direct mutations to the member table are strictly prevented.
     *
     * @param array<string, mixed> $newFields
     * @throws DomainException
     */
    public function submitGeneralUpdateRequest(
        Member $member,
        array $newFields,
        string $justification
    ): MemberChangeRequest {
        // Concurrency guard: Block if pending general update exists
        $hasPending = MemberChangeRequest::withoutGlobalScopes()
            ->where('member_id', $member->id)
            ->where('request_type', MemberChangeRequest::TYPE_GENERAL_UPDATE)
            ->where('status', MemberChangeRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            throw new DomainException('A profile change request is already pending review for this member.');
        }

        $trimmedJustification = trim($justification);
        if (mb_strlen($trimmedJustification) < 10) {
            throw new DomainException('A valid justification of at least 10 characters is required.');
        }

        $oldPayload = [];
        $filteredNewPayload = [];

        foreach (self::PERMITTED_GENERAL_FIELDS as $field) {
            if (! array_key_exists($field, $newFields)) {
                continue;
            }

            $currentVal = $member->{$field};
            $proposedVal = $newFields[$field];

            // Normalize strings for comparison
            $currentStr = $currentVal === null ? '' : trim((string) $currentVal);
            $proposedStr = $proposedVal === null ? '' : trim((string) $proposedVal);

            if ($currentStr !== $proposedStr) {
                $oldPayload[$field] = $currentVal;
                $filteredNewPayload[$field] = $proposedVal;
            }
        }

        if ($filteredNewPayload === []) {
            throw new DomainException('No profile attributes were modified in the submission.');
        }

        return DB::transaction(function () use ($member, $oldPayload, $filteredNewPayload, $trimmedJustification): MemberChangeRequest {
            $changeRequest = MemberChangeRequest::create([
                'workspace_id' => $member->workspace_id,
                'member_id' => $member->id,
                'request_type' => MemberChangeRequest::TYPE_GENERAL_UPDATE,
                'status' => MemberChangeRequest::STATUS_PENDING,
                'old_payload' => $oldPayload,
                'new_payload' => $filteredNewPayload,
                'justification' => $trimmedJustification,
            ]);

            $actorId = auth('member')->id() ?? auth()->id() ?? $member->user_id;
            $actorGuard = auth('member')->check() ? 'member' : (auth()->check() ? 'web' : 'system');

            AuditLog::create([
                'workspace_id' => $member->workspace_id,
                'actor_id' => $actorId,
                'actor_guard' => $actorGuard,
                'event_type' => AuditLog::EVENT_MEMBER_CHANGE_REQUEST_SUBMITTED,
                'auditable_type' => MemberChangeRequest::class,
                'auditable_id' => $changeRequest->id,
                'old_values' => $oldPayload,
                'new_values' => $filteredNewPayload,
                'reason' => $trimmedJustification,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'MemberPortal',
                'created_at' => now(),
            ]);

            return $changeRequest;
        });
    }

    /**
     * Submit an email change request for a member with strict system-wide uniqueness check.
     * Queues an alert notification to the Workspace Manager.
     *
     * @throws DomainException
     */
    public function submitEmailChangeRequest(
        Member $member,
        string $newEmail,
        string $justification
    ): MemberChangeRequest {
        $normalizedNewEmail = strtolower(trim($newEmail));
        $currentEmail = strtolower(trim((string) $member->email));

        // Format validation
        if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('The proposed email address format is invalid.');
        }

        // Must differ from current email
        if ($normalizedNewEmail === $currentEmail) {
            throw new DomainException('The new email address must differ from your current registered email.');
        }

        // Justification length check
        $trimmedJustification = trim($justification);
        if (mb_strlen($trimmedJustification) < 10) {
            throw new DomainException('A valid justification of at least 10 characters is required.');
        }

        // Concurrency guard: Ensure no pending email change request exists
        $hasPending = MemberChangeRequest::withoutGlobalScopes()
            ->where('member_id', $member->id)
            ->where('request_type', MemberChangeRequest::TYPE_EMAIL_CHANGE)
            ->where('status', MemberChangeRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            throw new DomainException('An email change request is already pending review for this member.');
        }

        // System-wide uniqueness check against members.email and users.email
        $existsInMembers = Member::withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [$normalizedNewEmail])
            ->exists();

        $existsInUsers = User::withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [$normalizedNewEmail])
            ->exists();

        if ($existsInMembers || $existsInUsers) {
            throw new DomainException('The requested email address is already in use by another account.');
        }

        $changeRequest = DB::transaction(function () use ($member, $normalizedNewEmail, $trimmedJustification): MemberChangeRequest {
            $request = MemberChangeRequest::create([
                'workspace_id' => $member->workspace_id,
                'member_id' => $member->id,
                'request_type' => MemberChangeRequest::TYPE_EMAIL_CHANGE,
                'status' => MemberChangeRequest::STATUS_PENDING,
                'old_payload' => ['email' => $member->email],
                'new_payload' => ['email' => $normalizedNewEmail],
                'justification' => $trimmedJustification,
            ]);

            $actorId = auth('member')->id() ?? auth()->id() ?? $member->user_id;
            $actorGuard = auth('member')->check() ? 'member' : (auth()->check() ? 'web' : 'system');

            AuditLog::create([
                'workspace_id' => $member->workspace_id,
                'actor_id' => $actorId,
                'actor_guard' => $actorGuard,
                'event_type' => AuditLog::EVENT_MEMBER_EMAIL_CHANGE_REQUESTED,
                'auditable_type' => MemberChangeRequest::class,
                'auditable_id' => $request->id,
                'old_values' => ['email' => $member->email],
                'new_values' => ['email' => $normalizedNewEmail],
                'reason' => $trimmedJustification,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'MemberPortal',
                'created_at' => now(),
            ]);

            return $request;
        });

        // Dispatch alert email to Workspace Manager
        $this->alertWorkspaceManagers($member, $normalizedNewEmail, $trimmedJustification);

        return $changeRequest;
    }

    /**
     * Atomically approve an email change request and synchronize both members and users tables.
     * Enforces that only Workspace Managers, Superadmins, or Global Admins can approve per DEC-07 and T-01-07.
     *
     * @throws AuthorizationException|DomainException
     */
    public function approveEmailChangeRequest(MemberChangeRequest $request, User $reviewerUser): void
    {
        $isElevatedAdmin = method_exists($reviewerUser, 'hasRole') && $reviewerUser->hasRole(['superadmin', 'global_admin']);
        $isWorkspaceManager = method_exists($reviewerUser, 'hasRole') && $reviewerUser->hasRole('workspace_manager');

        if (! $isElevatedAdmin && ! $isWorkspaceManager) {
            throw new AuthorizationException('Reviewer lacks Workspace Manager authority to approve email changes.');
        }

        if (! $isElevatedAdmin && (string) $reviewerUser->workspace_id !== (string) $request->workspace_id) {
            throw new AuthorizationException('Reviewer does not have access to this workspace.');
        }

        if ($request->request_type !== MemberChangeRequest::TYPE_EMAIL_CHANGE) {
            throw new DomainException("Request #{$request->id} is not an email change request.");
        }

        if ($request->status !== MemberChangeRequest::STATUS_PENDING) {
            throw new DomainException("Cannot approve a change request that is in status [{$request->status}].");
        }

        DB::transaction(function () use ($request, $reviewerUser): void {
            /** @var MemberChangeRequest $lockedRequest */
            $lockedRequest = MemberChangeRequest::withoutGlobalScopes()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== MemberChangeRequest::STATUS_PENDING) {
                throw new DomainException("Cannot approve a change request that is in status [{$lockedRequest->status}].");
            }

            /** @var Member $member */
            $member = Member::withoutGlobalScopes()
                ->where('id', $lockedRequest->member_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var User $user */
            $user = User::withoutGlobalScopes()
                ->where('id', $member->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $newEmail = strtolower(trim((string) ($lockedRequest->new_payload['email'] ?? '')));
            if (empty($newEmail) || ! filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('Invalid new email address in change request payload.');
            }

            // TOCTOU Race Condition Defense (T-09-12):
            // Re-evaluate email uniqueness inside the transaction lock immediately before applying the update.
            $existsInMembers = Member::withoutGlobalScopes()
                ->where('id', '!=', $member->id)
                ->whereRaw('LOWER(email) = ?', [$newEmail])
                ->exists();

            $existsInUsers = User::withoutGlobalScopes()
                ->where('id', '!=', $user->id)
                ->whereRaw('LOWER(email) = ?', [$newEmail])
                ->exists();

            if ($existsInMembers || $existsInUsers) {
                throw new DomainException('The requested new email has been claimed by another user prior to approval.');
            }

            $oldEmail = $member->email;

            // Atomic Dual-Entity Update
            $member->email = $newEmail;
            $member->save();

            $user->email = $newEmail;
            $user->save();

            // Transition request status
            $lockedRequest->update([
                'status' => MemberChangeRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $reviewerUser->id,
                'reviewed_at' => now(),
            ]);

            // Emit immutable AuditLog entry per DEC-11 (D-11)
            AuditLog::create([
                'workspace_id' => $member->workspace_id,
                'actor_id' => $reviewerUser->id,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_MEMBER_EMAIL_CHANGED,
                'auditable_type' => Member::class,
                'auditable_id' => $member->id,
                'old_values' => ['email' => $oldEmail],
                'new_values' => ['email' => $newEmail],
                'reason' => "Manager approved email change request #{$lockedRequest->id}: {$lockedRequest->justification}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'created_at' => now(),
            ]);

            // Trigger workflow rule
            $this->dispatchWorkflowOutcome($lockedRequest, $member, 'approved', '');
        });
    }

    /**
     * Atomically approve a pending change request and apply changes to the member profile.
     *
     * @throws AuthorizationException|DomainException
     */
    public function approveRequest(MemberChangeRequest $request, User $reviewerUser): Member
    {
        if ($request->request_type === MemberChangeRequest::TYPE_EMAIL_CHANGE) {
            $this->approveEmailChangeRequest($request, $reviewerUser);
            return Member::withoutGlobalScopes()->findOrFail($request->member_id);
        }

        $this->assertReviewerAuthorized($reviewerUser, $request);

        return DB::transaction(function () use ($request, $reviewerUser): Member {
            /** @var MemberChangeRequest $lockedRequest */
            $lockedRequest = MemberChangeRequest::withoutGlobalScopes()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== MemberChangeRequest::STATUS_PENDING) {
                throw new DomainException("Cannot approve a change request that is in status [{$lockedRequest->status}].");
            }

            /** @var Member $lockedMember */
            $lockedMember = Member::withoutGlobalScopes()
                ->where('id', $lockedRequest->member_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->request_type === MemberChangeRequest::TYPE_GENERAL_UPDATE) {
                // Ensure only permitted fields are updated
                $safePayload = array_intersect_key(
                    $lockedRequest->new_payload ?? [],
                    array_flip(self::PERMITTED_GENERAL_FIELDS)
                );
                $lockedMember->update($safePayload);
            }

            $lockedRequest->update([
                'status' => MemberChangeRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $reviewerUser->id,
                'reviewed_at' => now(),
            ]);

            AuditLog::create([
                'workspace_id' => $lockedRequest->workspace_id,
                'actor_id' => $reviewerUser->id,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_MEMBER_CHANGE_REQUEST_APPROVED,
                'auditable_type' => MemberChangeRequest::class,
                'auditable_id' => $lockedRequest->id,
                'old_values' => $lockedRequest->old_payload,
                'new_values' => $lockedRequest->new_payload,
                'reason' => 'Staff approved member change request.',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'created_at' => now(),
            ]);

            // Dispatch notification via workflow engine
            $this->dispatchWorkflowOutcome($lockedRequest, $lockedMember, 'approved', '');

            return $lockedMember->fresh();
        });
    }

    /**
     * Reject a pending change request with mandatory manager notes.
     *
     * @throws AuthorizationException|DomainException
     */
    public function rejectRequest(
        MemberChangeRequest $request,
        User $reviewerUser,
        string $managerNotes
    ): MemberChangeRequest {
        $this->assertReviewerAuthorized($reviewerUser, $request);

        $trimmedNotes = trim($managerNotes);
        if (mb_strlen($trimmedNotes) < 5) {
            throw new DomainException('Manager notes of at least 5 characters are required for rejection.');
        }

        return DB::transaction(function () use ($request, $reviewerUser, $trimmedNotes): MemberChangeRequest {
            /** @var MemberChangeRequest $lockedRequest */
            $lockedRequest = MemberChangeRequest::withoutGlobalScopes()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== MemberChangeRequest::STATUS_PENDING) {
                throw new DomainException("Cannot reject a change request that is in status [{$lockedRequest->status}].");
            }

            $lockedRequest->update([
                'status' => MemberChangeRequest::STATUS_REJECTED,
                'reviewed_by_user_id' => $reviewerUser->id,
                'reviewed_at' => now(),
                'manager_notes' => $trimmedNotes,
            ]);

            AuditLog::create([
                'workspace_id' => $lockedRequest->workspace_id,
                'actor_id' => $reviewerUser->id,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_MEMBER_CHANGE_REQUEST_REJECTED,
                'auditable_type' => MemberChangeRequest::class,
                'auditable_id' => $lockedRequest->id,
                'old_values' => $lockedRequest->old_payload,
                'new_values' => [
                    'status' => MemberChangeRequest::STATUS_REJECTED,
                    'manager_notes' => $trimmedNotes,
                ],
                'reason' => $trimmedNotes,
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'created_at' => now(),
            ]);

            $member = $lockedRequest->member;
            if ($member) {
                $this->dispatchWorkflowOutcome($lockedRequest, $member, 'rejected', $trimmedNotes);
            }

            return $lockedRequest->fresh();
        });
    }

    /**
     * Assert that reviewer has valid permissions and tenancy authorization.
     *
     * @throws AuthorizationException
     */
    protected function assertReviewerAuthorized(User $reviewer, MemberChangeRequest $request): void
    {
        $isSuper = method_exists($reviewer, 'hasRole') && $reviewer->hasRole('superadmin');
        $isGlobalAdmin = method_exists($reviewer, 'hasRole') && $reviewer->hasRole('global_admin');

        if (! $isSuper && ! $isGlobalAdmin) {
            if ((string) $reviewer->workspace_id !== (string) $request->workspace_id) {
                throw new AuthorizationException('Reviewer does not have access to this workspace.');
            }

            $canEdit = $reviewer->can('members.edit')
                || (method_exists($reviewer, 'hasRole') && $reviewer->hasRole('workspace_manager'));

            if (! $canEdit) {
                throw new AuthorizationException('Reviewer lacks members.edit or workspace_manager authority.');
            }
        }
    }

    /**
     * Trigger email notification for change request outcome.
     */
    protected function dispatchWorkflowOutcome(
        MemberChangeRequest $request,
        Member $member,
        string $outcome,
        string $managerNotes
    ): void {
        try {
            $engine = $this->workflowEngine ?? app(WorkflowRuleEngine::class);
            if ($engine && $member->email) {
                $engine->trigger(
                    workspaceId: (string) $request->workspace_id,
                    triggerEvent: WorkflowRule::TRIGGER_REQUEST_OUTCOME,
                    recipientEmail: (string) $member->email,
                    variables: [
                        'first_name' => $member->first_name,
                        'member_id' => $member->member_id,
                        'request_type' => $request->request_type,
                        'outcome' => $outcome,
                        'manager_notes' => $managerNotes,
                    ],
                    memberId: $member->id,
                );
            }
        } catch (\Throwable $e) {
            // Workflow rule might not be configured; do not fail transaction
        }
    }

    /**
     * Alert Workspace Managers of a new email change request.
     */
    protected function alertWorkspaceManagers(Member $member, string $newEmail, string $justification): void
    {
        $managers = User::where('workspace_id', $member->workspace_id)
            ->where(function ($q) {
                $q->whereHas('roles', function ($rq) {
                    $rq->whereIn('name', ['workspace_manager', 'superadmin', 'global_admin']);
                });
            })
            ->get();

        if ($managers->isEmpty()) {
            $managers = User::whereHas('roles', function ($rq) {
                $rq->whereIn('name', ['workspace_manager', 'superadmin', 'global_admin']);
            })->get();
        }

        if ($managers->isEmpty()) {
            $managers = User::where('workspace_id', $member->workspace_id)
                ->where('guard', 'web')
                ->get();
        }

        $recipients = $managers->isNotEmpty() ? $managers : 'manager@emk.test';

        Mail::to($recipients)->queue(new ManagerEmailChangeAlertMail($member, $newEmail, $justification));
    }
}
