---
phase: 09-read-only-member-portal-change-requests-and-renewals
plan: 03
status: complete
subsystem: portal
tags: [member-portal, change-requests, diff-inspector, approval-workflow, concurrency-guard, audit-logging, livewire]

requires:
  - phase: 09-read-only-member-portal-change-requests-and-renewals
    plan: 02
    provides: Read-only member portal profile interface, secure document streaming, and communication history

provides:
  - MemberChangeRequest domain model wrapping member_change_requests table with workspace tenancy
  - MemberChangeRequestService implementing submission, concurrency guarding, atomic approval, rejection, and workflow triggers
  - SubmitProfileChangeRequestModal Livewire component and Blade view for member self-service submission
  - MemberChangeRequestDirectory Livewire component with side-by-side diff inspector and manager decision actions
  - crm.members.change_requests CRM route registered in routes/web.php
  - Comprehensive feature test suite in tests/Feature/MemberChangeRequestTest.php

affects:
  - 09-04-PLAN (Membership Renewal Workflow & bKash Fee Submission)
  - 09-05-PLAN (Dedicated Dual-Token Email Change Flow per DEC-07)

actuals:
  tokens: 28860
  tasks: 2
  commits: 2
  plan_head_before: cbc1b246ee0bdecd316be037d51664f23ed9d85c
  plan_head_after: 04ef8a6600b0a25793df70c0ff593d3bdecf0414

tech-stack:
  added: []
  patterns:
    - "Pessimistic row locking (lockForUpdate) inside atomic database transactions for approval/rejection state transitions"
    - "Strict field whitelisting preventing unauthorized mutation of core identity attributes (first_name, date_of_birth, etc.)"
    - "Concurrency guard blocking duplicate pending general update requests per member"
    - "Immutable audit logging capturing old and new attribute diff payloads with actor attribution"
    - "Side-by-side diff inspection with visual modification highlights for staff review"

key-files:
  created:
    - app/Domain/Members/Models/MemberChangeRequest.php
    - app/Domain/Members/Services/MemberChangeRequestService.php
    - app/Livewire/Portal/Modals/SubmitProfileChangeRequestModal.php
    - resources/views/livewire/portal/modals/submit-profile-change-request-modal.blade.php
    - app/Livewire/Members/MemberChangeRequestDirectory.php
    - resources/views/livewire/members/member-change-request-directory.blade.php
    - tests/Feature/MemberChangeRequestTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Members/Models/Member.php
    - resources/views/livewire/portal/member-portal-profile.blade.php
    - routes/web.php

key-decisions:
  - "Enforced DEC-06 (D-06): Direct member profile database mutations strictly prevented; all self-service edits queued in member_change_requests in pending status until reviewed by staff"
  - "Enforced DEC-11 (D-11): Immutable audit logging for submission (member_change_request_submitted), approval (member_change_request_approved), and rejection (member_change_request_rejected) with actor and payload tracking"
  - "Restricted change request approval/rejection authority strictly to Workspace Managers and Superadmin per DECISION-REGISTER Priority 2 and T-09-09"
  - "Configured pessimistic lockForUpdate() during approval transition to eliminate race conditions (T-09-08)"
---

# Phase 09 Plan 03: Member Profile Change Request Engine, Diff Inspector & Administrative Approval Summary

Self-service Member Profile Change Request domain engine, duplicate submission guards, portal submission modal, and CRM Workspace Manager review directory with side-by-side diff inspection and atomic approval/rejection state transitions per PORT-03, DEC-06 (D-06), DEC-11 (D-11), and DECISION-REGISTER.md.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | MemberChangeRequest Domain Model, Submission Service & Portal Modal with Duplication Guard | `b1bafe3` | `app/Domain/Members/Models/MemberChangeRequest.php`, `app/Domain/Members/Services/MemberChangeRequestService.php`, `app/Livewire/Portal/Modals/SubmitProfileChangeRequestModal.php`, `resources/views/livewire/portal/modals/submit-profile-change-request-modal.blade.php`, `resources/views/livewire/portal/member-portal-profile.blade.php`, `tests/Feature/MemberChangeRequestTest.php` |
| 2 | CRM Staff Change Request Review Directory, Diff Inspector & Atomic Approval State Transition | `04ef8a6` | `app/Livewire/Members/MemberChangeRequestDirectory.php`, `resources/views/livewire/members/member-change-request-directory.blade.php`, `routes/web.php`, `tests/Feature/MemberChangeRequestTest.php` |

## Key Changes & Implementations

### 1. Member Profile Change Request Domain Model (`MemberChangeRequest`)
- Wraps `member_change_requests` table with tenant isolation via `BelongsToTenant` and `TenantEntityInterface`.
- Casts `old_payload` and `new_payload` to arrays and `reviewed_at` to datetime.
- Establishes status constants (`pending`, `approved`, `rejected`) and request type constants (`general_update`, `email_change`, `renewal`).
- Defines relationships: `member()` (`Member::class`), `reviewer()` (`User::class`).
- Adds `changeRequests()` relationship on `Member` model.

### 2. Submission & Concurrency Guard Engine (`MemberChangeRequestService`)
- Method `submitGeneralUpdateRequest(Member $member, array $newFields, string $justification)`:
  - Concurrency Guard (T-09-08): Checks for any existing pending general update request for the member; throws `DomainException` preventing concurrent duplicate submissions.
  - Whitelists permitted editable fields: `contact_number`, `address_house_no`, `address_road_no`, `address_area`, `address_zip_code`, `address_division`, `address_district`, `occupation`, `institution_study`, `highest_certification`, and `area_of_subject`.
  - Discards unauthorized attempts to modify immutable core identity attributes (`id`, `uuid`, `member_id`, `first_name`, `date_of_birth`, `status`).
  - Calculates diff between current database values and submitted values; rejects submissions with no modifications.
  - Enforces mandatory justification (min 10 characters).
  - Emits immutable `AuditLog` entry with `event_type = member_change_request_submitted`.
- Method `approveRequest(MemberChangeRequest $request, User $reviewerUser)`:
  - Enforces authorization: reviewer must have `members.edit` permission or role in `['workspace_manager', 'global_admin', 'superadmin']` and match workspace tenancy.
  - Runs inside `DB::transaction()` with pessimistic `lockForUpdate()` on both request and member records.
  - Applies proposed attributes to `members` table atomically.
  - Transitions request to `approved` status, logs reviewer and timestamp.
  - Emits immutable `AuditLog` entry with `event_type = member_change_request_approved`.
  - Triggers workflow notification event `WorkflowRule::TRIGGER_REQUEST_OUTCOME`.
- Method `rejectRequest(MemberChangeRequest $request, User $reviewerUser, string $managerNotes)`:
  - Requires mandatory explanation notes (min 5 characters).
  - Transitions request to `rejected` status with manager notes.
  - Leaves `members` table untouched.
  - Emits immutable `AuditLog` entry with `event_type = member_change_request_rejected`.
  - Triggers workflow notification event `WorkflowRule::TRIGGER_REQUEST_OUTCOME`.

### 3. Member Portal Submission Modal (`SubmitProfileChangeRequestModal`)
- Livewire modal mounted inside Member Portal profile page.
- Pre-populates contact, address, and academic/professional attributes from current member record.
- Displays policy warning explaining administrative verification requirements.
- Automatically disables submission and displays warning banner when a pending change request already exists.
- Captures justification textarea and invokes `MemberChangeRequestService::submitGeneralUpdateRequest`.
- Flashes success confirmation: "Your change request has been submitted to workspace staff for review."

### 4. CRM Staff Change Request Directory & Diff Inspector (`MemberChangeRequestDirectory`)
- Bound to `/crm/{workspace}/members/change-requests` under `crm.members.change_requests` route.
- Protected by `auth:web`, `workspace.access`, `password.reset`, and `can:members.view`.
- Tenancy isolation via `ValidatesWorkspaceOnHydration`.
- Real-time KPI summary strip: Total Requests, Pending Review, Approved, Rejected.
- Reactive filters: Status (`pending`, `approved`, `rejected`, `all`), Request Type, and keyword search across member ID, name, and email.
- Side-by-side Diff Inspector Modal:
  - Compares original database attribute values against proposed values with green visual highlighting.
  - Shows full member justification.
  - For pending requests, provides "Approve Changes" and "Reject Request" (with mandatory rejection notes) action buttons for authorized Workspace Managers.
  - For decided requests, displays reviewer identity, decision timestamp, and recorded manager notes.

## Verification & Test Results

`php artisan test tests/Feature/MemberChangeRequestTest.php` executed with 15 passed tests (66 assertions, 100% pass):
- `test_submitting_change_request_creates_pending_record_without_mutating_member_profile` (PASS)
- `test_submitting_change_request_with_no_changes_throws_exception` (PASS)
- `test_duplicate_pending_change_request_is_strictly_blocked` (PASS)
- `test_non_permitted_fields_cannot_be_manipulated_in_change_request` (PASS)
- `test_submitting_change_request_requires_valid_justification` (PASS)
- `test_portal_modal_renders_and_submits_change_request_via_livewire` (PASS)
- `test_portal_modal_disables_submission_when_pending_request_exists` (PASS)
- `test_staff_can_view_workspace_pending_change_requests_directory` (PASS)
- `test_cross_workspace_staff_cannot_access_or_review_requests` (PASS)
- `test_approving_change_request_updates_member_record_and_emits_audit_log` (PASS)
- `test_rejecting_change_request_requires_notes_and_leaves_member_intact` (PASS)
- `test_already_decided_request_cannot_be_approved_or_rejected_again` (PASS)
- `test_staff_without_edit_permission_cannot_approve_request` (PASS)
- `test_change_request_directory_diff_modal_and_approval_via_livewire` (PASS)
- `test_change_request_directory_rejection_via_livewire` (PASS)

Regression verification suite:
- `MemberChangeRequestTest` (15 passed)
- `PortalReadOnlyProfileTest` (11 passed)
- `PortalAuthenticationAndTracerTest` (14 passed)
- `MemberDirectoryTest` (6 passed)
- `ModuleRegistryArchTest` (3 passed)
- Total: 49 passed tests, 249 assertions.

## Deviations from Plan

None - plan executed exactly as written.

## Threat Model Verification

- **T-09-07 (Tampering / Direct Profile Mutation):** Self-service member updates strictly create pending queue entries in `member_change_requests`. Direct mutation of `members` database table is strictly blocked. Verified via `test_submitting_change_request_creates_pending_record_without_mutating_member_profile` and `test_non_permitted_fields_cannot_be_manipulated_in_change_request`.
- **T-09-08 (Race Condition / Concurrency):** Duplicate pending requests are rejected at service and UI levels. State transitions use `lockForUpdate()` inside atomic database transactions. Verified via `test_duplicate_pending_change_request_is_strictly_blocked` and `test_already_decided_request_cannot_be_approved_or_rejected_again`.
- **T-09-09 (Elevation of Privilege):** Staff without `members.edit` permission or Workspace Manager authority cannot approve change requests. Verified via `test_staff_without_edit_permission_cannot_approve_request` and `test_cross_workspace_staff_cannot_access_or_review_requests`.

## Self-Check: PASSED
- `app/Domain/Members/Models/MemberChangeRequest.php` FOUND
- `app/Domain/Members/Services/MemberChangeRequestService.php` FOUND
- `app/Livewire/Portal/Modals/SubmitProfileChangeRequestModal.php` FOUND
- `resources/views/livewire/portal/modals/submit-profile-change-request-modal.blade.php` FOUND
- `app/Livewire/Members/MemberChangeRequestDirectory.php` FOUND
- `resources/views/livewire/members/member-change-request-directory.blade.php` FOUND
- `tests/Feature/MemberChangeRequestTest.php` FOUND
- Commits `b1bafe3` and `04ef8a6` verified in git history
