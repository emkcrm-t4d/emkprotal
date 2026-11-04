---
phase: 09-read-only-member-portal-change-requests-and-renewals
plan: 04
status: complete
subsystem: portal
tags: [member-portal, email-change, security-gate, manager-alert, toctou-guard, dual-entity-sync, audit-logging, livewire]

requires:
  - phase: 09-read-only-member-portal-change-requests-and-renewals
    plan: 03
    provides: MemberChangeRequest domain model, service submission and approval framework, and CRM change requests review directory

provides:
  - ManagerEmailChangeAlertMail queued mailable alerting workspace managers upon submission
  - SubmitEmailChangeModal Livewire portal component with cross-table uniqueness validation and duplicate prevention
  - submitEmailChangeRequest method in MemberChangeRequestService validating format, current email diff, and members+users uniqueness
  - approveEmailChangeRequest method in MemberChangeRequestService with TOCTOU re-validation, lockForUpdate pessimistic locking, and atomic dual-entity synchronization across members and users tables
  - MemberChangeRequestDirectory enhanced with amber security badge and role-restricted approval authority
  - Comprehensive 15-test feature test suite in tests/Feature/MemberEmailChangeTest.php

affects:
  - 09-05-PLAN (Membership Renewal Workflow & bKash Fee Submission)

actuals:
  tokens: 17736
  tasks: 2
  commits: 2
  plan_head_before: 04ef8a6600b0a25793df70c0ff593d3bdecf0414
  plan_head_after: c41522fc2232ec7411a6c81b6e6d5f35c2338694

tech-stack:
  added: []
  patterns:
    - "Dual-entity synchronization atomically updating members.email and users.email inside a DB transaction"
    - "TOCTOU race condition defense re-asserting email uniqueness inside pessimistic row locks immediately before applying updates"
    - "Strict role-based approval gating restricting email changes exclusively to Workspace Managers, Superadmins, or Global Admins"
    - "Automated queued security alerts dispatching to Workspace Managers upon change submission"
    - "Immutable audit trail logging member_email_change_requested and member_email_changed events with previous and new values"

key-files:
  created:
    - app/Domain/Members/Mail/ManagerEmailChangeAlertMail.php
    - resources/views/emails/members/manager-email-change-alert.blade.php
    - app/Livewire/Portal/Modals/SubmitEmailChangeModal.php
    - resources/views/livewire/portal/modals/submit-email-change-modal.blade.php
    - tests/Feature/MemberEmailChangeTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Members/Services/MemberChangeRequestService.php
    - app/Livewire/Members/MemberChangeRequestDirectory.php
    - resources/views/livewire/members/member-change-request-directory.blade.php
    - resources/views/livewire/portal/member-portal-profile.blade.php

key-decisions:
  - "Enforced PORT-04 & DEC-07 (D-07): Dedicated email change workflow separate from general demographic updates, requiring mandatory justification and manager authorization"
  - "Enforced T-09-10 & DEC-07: Approval authority restricted strictly to Workspace Managers and Superadmins; delegated staff with general members.edit permission are explicitly blocked"
  - "Enforced T-09-11: Atomic dual-entity synchronization wrapped in pessimistic lockForUpdate() ensuring members.email and users.email never diverge"
  - "Enforced T-09-12: TOCTOU race condition prevention re-evaluates email uniqueness inside transaction lock prior to executing update"
  - "Enforced DEC-11 (D-11): Immutable audit logs recording event types member_email_change_requested and member_email_changed"
---

# Phase 09 Plan 04: Dedicated Email Change Request Workflow, Manager Alerting & Atomic Dual-Entity Synchronization Summary

Dedicated Member Portal Email Change Request workflow, Workspace Manager queued email alerts, strict cross-table uniqueness validation, and atomic dual-entity synchronization with TOCTOU concurrency defense per PORT-04, DEC-06 (D-06), DEC-07 (D-07), and DEC-11 (D-11).

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Member Portal Email Change Submission with Strict Uniqueness Validation & Manager Alert | `c8c9c7b` | `app/Domain/Members/Mail/ManagerEmailChangeAlertMail.php`, `resources/views/emails/members/manager-email-change-alert.blade.php`, `app/Livewire/Portal/Modals/SubmitEmailChangeModal.php`, `resources/views/livewire/portal/modals/submit-email-change-modal.blade.php`, `app/Domain/Members/Services/MemberChangeRequestService.php`, `resources/views/livewire/portal/member-portal-profile.blade.php`, `tests/Feature/MemberEmailChangeTest.php` |
| 2 | Workspace Manager Email Change Approval Gate & Atomic Dual-Entity Synchronization | `c41522f` | `app/Domain/Members/Services/MemberChangeRequestService.php`, `app/Livewire/Members/MemberChangeRequestDirectory.php`, `resources/views/livewire/members/member-change-request-directory.blade.php`, `tests/Feature/MemberEmailChangeTest.php` |

## Key Changes & Implementations

### 1. Queued Security Alert Mailable (`ManagerEmailChangeAlertMail`)
- Created queued mailable (`ShouldQueue`) dispatched when an email change request is submitted.
- Subject: `[EMK Member Portal] Security Alert: Email Change Requested for {member_id}`.
- Markdown template provides: Member Name, Member ID, Current Registered Email, Proposed New Email, Justification, Timestamp, and direct action link to `/crm/{workspace}/members/change-requests`.

### 2. Email Change Submission & Cross-Table Uniqueness Guard (`MemberChangeRequestService`)
- Added `submitEmailChangeRequest(Member $member, string $newEmail, string $justification): MemberChangeRequest`:
  - Validates email address format via `filter_var(..., FILTER_VALIDATE_EMAIL)`.
  - Asserts proposed new email differs from current registered email.
  - Enforces mandatory justification length of >= 10 characters.
  - Concurrency Guard (T-09-08): Blocks submission if an email change request is already pending review for the member.
  - System-wide uniqueness check: Queries both `members` and `users` tables (`withoutGlobalScopes()`) using case-insensitive email match; rejects if email is registered on either entity.
  - Creates `MemberChangeRequest` with `request_type = 'email_change'`, `status = 'pending'`, `old_payload = ['email' => ...]`, and `new_payload = ['email' => ...]`.
  - Emits immutable `AuditLog` entry with `event_type = member_email_change_requested`.
  - Dispatches `ManagerEmailChangeAlertMail` to workspace managers.

### 3. Member Portal Email Change Livewire Modal (`SubmitEmailChangeModal`)
- Modal mounted in Member Portal profile page triggered by "Change Email" button next to email field.
- Reactive live validation on `new_email` asserting valid format, difference from current email, and uniqueness across `members` and `users`.
- Confirmation email field validation (`same:new_email`).
- Detects existing pending requests and renders warning banner with disabled inputs.
- Flashes success feedback: "Email change request submitted. Your Workspace Manager has been notified for security review."

### 4. Workspace Manager Approval Gate & Atomic Synchronization (`approveEmailChangeRequest`)
- Strict authorization check: Only users with `workspace_manager`, `superadmin`, or `global_admin` role can approve email changes. Delegated staff with `members.edit` permission are strictly blocked per DEC-07 and T-09-10.
- Executed inside `DB::transaction()`:
  - Pessimistic row locking with `lockForUpdate()` on `MemberChangeRequest`, `Member`, and linked `User` records.
  - TOCTOU Race Condition Defense (T-09-12): Re-asserts that proposed email has not been claimed by another user or member between submission and approval.
  - Atomically mutates `$member->email` and `$user->email` in the same transaction.
  - Transitions request status to `approved`, recording reviewer user and timestamp.
  - Emits immutable `AuditLog` entry with `event_type = member_email_changed`, capturing old and new email addresses.
  - Triggers workflow notification event `WorkflowRule::TRIGGER_REQUEST_OUTCOME`.

### 5. CRM Change Request Directory Enhancements (`MemberChangeRequestDirectory`)
- Highlights email change requests with distinct amber security badge: "Email Address Change (Requires Manager Approval)".
- Displays security alert banner inside the Diff Inspector Modal outlining credential impacts.
- Restricts the "Approve" button to Workspace Managers and Superadmins; displays disabled "Approve (Manager Only)" for unauthorized staff.
- Emits success flash on approval: "Member email successfully updated across portal credentials and member profile."

## Verification & Test Results

### `tests/Feature/MemberEmailChangeTest.php` (15 passed, 65 assertions):
- `test_member_can_submit_valid_email_change_request_with_justification` (PASS)
- `test_submitting_already_registered_email_in_members_table_throws_exception` (PASS)
- `test_submitting_already_registered_email_in_users_table_throws_exception` (PASS)
- `test_submitting_duplicate_pending_email_change_request_is_blocked` (PASS)
- `test_submitting_invalid_email_format_or_identical_email_throws_exception` (PASS)
- `test_submitting_short_justification_throws_exception` (PASS)
- `test_manager_email_change_alert_mail_is_queued_upon_submission` (PASS)
- `test_portal_email_change_modal_renders_and_submits_via_livewire` (PASS)
- `test_portal_email_change_modal_blocks_submission_when_pending_request_exists` (PASS)
- `test_delegated_staff_cannot_approve_email_change_request` (PASS)
- `test_workspace_manager_approves_email_change_successfully_and_synchronizes_both_tables` (PASS)
- `test_toctou_race_condition_blocks_approval_if_email_claimed_prior_to_approval` (PASS)
- `test_member_can_immediately_log_in_with_new_email_address_and_old_email_fails` (PASS)
- `test_livewire_change_request_directory_diff_modal_and_approval_by_manager` (PASS)
- `test_livewire_change_request_directory_restricts_approval_for_delegated_staff` (PASS)

### Comprehensive Regression Suite (55 passed, 258 assertions):
- `MemberEmailChangeTest` (15 passed)
- `MemberChangeRequestTest` (15 passed)
- `PortalReadOnlyProfileTest` (11 passed)
- `PortalAuthenticationAndTracerTest` (14 passed)

## Deviations from Plan

None - plan executed exactly as written.

## Threat Model Verification

- **T-09-10 (Elevation of Privilege / Account Takeover):** Email change approval authority is restricted exclusively to Workspace Managers, Superadmins, and Global Admins. Delegated staff attempts are blocked at both service level (`AuthorizationException`) and Livewire UI level. Verified by `test_delegated_staff_cannot_approve_email_change_request` and `test_livewire_change_request_directory_restricts_approval_for_delegated_staff`.
- **T-09-11 (Data Inconsistency / Denial of Service):** Dual-entity update of `members.email` and `users.email` is wrapped in an atomic database transaction with `lockForUpdate()` pessimistic row locking, guaranteeing credentials and member profile never diverge. Verified by `test_workspace_manager_approves_email_change_successfully_and_synchronizes_both_tables`.
- **T-09-12 (TOCTOU Race Condition):** Uniqueness is re-evaluated inside the transaction lock immediately before applying the update. If an email address is claimed between submission and approval, the transaction safely aborts with `DomainException`. Verified by `test_toctou_race_condition_blocks_approval_if_email_claimed_prior_to_approval`.

## Self-Check: PASSED
- `app/Domain/Members/Mail/ManagerEmailChangeAlertMail.php` FOUND
- `resources/views/emails/members/manager-email-change-alert.blade.php` FOUND
- `app/Livewire/Portal/Modals/SubmitEmailChangeModal.php` FOUND
- `resources/views/livewire/portal/modals/submit-email-change-modal.blade.php` FOUND
- `app/Domain/Members/Services/MemberChangeRequestService.php` FOUND
- `app/Livewire/Members/MemberChangeRequestDirectory.php` FOUND
- `resources/views/livewire/members/member-change-request-directory.blade.php` FOUND
- `resources/views/livewire/portal/member-portal-profile.blade.php` FOUND
- `tests/Feature/MemberEmailChangeTest.php` FOUND
- Commit `c8c9c7b` (Task 1) verified in git history
- Commit `c41522f` (Task 2) verified in git history
