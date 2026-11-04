---
phase: 09-read-only-member-portal-change-requests-and-renewals
plan: 05
status: complete
subsystem: portal
tags: [member-portal, renewals, bkash, 30-day-window, expiry-mathematics, paid-invoices, e2e-testing, integration]

requires:
  - phase: 09-read-only-member-portal-change-requests-and-renewals
    plan: 04
    provides: MemberChangeRequest domain framework, email change and general update flows, and CRM change requests directory

provides:
  - MemberRenewalService domain service with 30-day window check, 10-character bKash validation, and early/late expiry mathematics
  - SubmitRenewalModal Livewire portal component with active terms selection and bKash TxID uppercase formatting
  - MemberPortalProfile enhanced with 30-day validity status and renewal action trigger
  - MemberChangeRequestDirectory enhanced with blue Membership Renewal badge, real-time diff calculation, and approval action
  - MemberRenewalWorkflowTest feature test suite (15 tests, 60 assertions)
  - EndToEndMemberPortalTest holistic integration test suite covering PORT-01 through PORT-05 (5 scenarios, 63 assertions)

affects: []

actuals:
  tokens: 23914
  tasks: 3
  commits: 3
  plan_head_before: c41522f
  plan_head_after: b5d4642

tech-stack:
  added: []
  patterns:
    - "Temporal validity gating strictly enforcing 30-day renewal submission window on server and client"
    - "Dynamic early vs late renewal expiry mathematics preserving 100% of remaining days on early payment and restarting validity from approval date on late renewals"
    - "Atomic automated post-renewal paid invoice issuance with sequential INV-{YEAR}-{MEMBER_SEQ_5}-R numbering referencing bKash payment transaction"
    - "Immutable audit logging capturing member_renewal_submitted and member_renewal_approved events"
    - "Holistic sequential integration testing exercising complete multi-step user journeys across authentication, read-only presentation, profile change, email change, and renewals"

key-files:
  created:
    - app/Domain/Members/Services/MemberRenewalService.php
    - app/Livewire/Portal/Modals/SubmitRenewalModal.php
    - resources/views/livewire/portal/modals/submit-renewal-modal.blade.php
    - tests/Feature/MemberRenewalWorkflowTest.php
    - tests/Feature/EndToEndMemberPortalTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Members/Services/MemberChangeRequestService.php
    - app/Livewire/Members/MemberChangeRequestDirectory.php
    - resources/views/livewire/members/member-change-request-directory.blade.php
    - resources/views/livewire/portal/member-portal-profile.blade.php

key-decisions:
  - "Enforced PORT-05 and STATUS-DICTIONARY.md 7.2: Active members may submit renewals within 30 calendar days before expiry date; expired (inactive) members may renew at any time; revoked members cannot renew"
  - "Enforced DEC-05 (D-05) & T-09-13: Strict 10-character uppercase alphanumeric bKash Transaction ID validation with system-wide uniqueness across leads, invoices, and pending requests"
  - "Enforced STATUS-DICTIONARY.md 7.2 & T-09-15: Early renewal mathematics adds term duration directly to existing expiration date ensuring zero days lost; late renewal starts validity on approval date"
  - "Enforced DEC-03 (D-03) & DEC-11 (D-11): Approving renewal automatically issues a paid Invoice record with bKash payment reference and creates an immutable AuditLog entry"
  - "Integrated all 5 PORT requirements into a holistic sequential end-to-end integration suite verifying authentication, information disclosure isolation, profile change, email change, and renewals"
---

# Phase 09 Plan 05: Membership Renewal Workflow, Dynamic Expiry Mathematics & Holistic Phase 9 Integration Suite Summary

Membership Renewal Request workflow, 30-day temporal window gate, manual bKash TxID verification, early vs late renewal expiry mathematics, post-renewal paid invoice issuance, and holistic Phase 9 end-to-end integration test suite per PORT-05, DEC-03 (D-03), DEC-05 (D-05), DEC-08 (D-08), DEC-11 (D-11), and STATUS-DICTIONARY.md Section 7.2.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Portal Renewal Submission Form, 30-Day Expiry Window Gate & 10-char bKash Validation | `da8a6a9` | `app/Domain/Audit/Models/AuditLog.php`, `app/Domain/Members/Services/MemberRenewalService.php`, `app/Livewire/Portal/Modals/SubmitRenewalModal.php`, `resources/views/livewire/portal/modals/submit-renewal-modal.blade.php`, `resources/views/livewire/portal/member-portal-profile.blade.php`, `tests/Feature/MemberRenewalWorkflowTest.php` |
| 2 | Staff Renewal Verification, Early vs Late Expiry Mathematics & Post-Renewal Paid Invoice | `1dfc858` | `app/Domain/Members/Services/MemberRenewalService.php`, `app/Domain/Members/Services/MemberChangeRequestService.php`, `app/Livewire/Members/MemberChangeRequestDirectory.php`, `resources/views/livewire/members/member-change-request-directory.blade.php`, `tests/Feature/MemberRenewalWorkflowTest.php` |
| 3 | Holistic Phase 9 End-to-End Integration Suite Covering All PORT Requirements & Edge Scenarios | `b5d4642` | `tests/Feature/EndToEndMemberPortalTest.php` |

## Key Changes & Implementations

### 1. Domain Renewal Service (`MemberRenewalService`)
- **Temporal Window Gate (`isRenewalWindowOpen`):**
  - Active members can renew when remaining calendar days `<= 30`.
  - Inactive (expired) members can renew at any time.
  - Revoked members are permanently blocked.
- **Submission Validation (`submitRenewalRequest`):**
  - Prevents premature submission when renewal window is closed.
  - Concurrency guard preventing multiple pending renewal requests.
  - Validates active `MembershipTerm` for the member's workspace.
  - Enforces exact 10-character uppercase alphanumeric bKash regex (`/^[A-Z0-9]{10}$/`).
  - Enforces system-wide uniqueness across `leads.bkash_txid`, `invoices.payment_reference`, and pending `member_change_requests.new_payload->bkash_txid`.
  - Emits immutable `AuditLog` entry with `event_type = member_renewal_submitted`.
- **Dynamic Expiry Calculations (`approveRenewal`):**
  - **Early Renewal Math:** If `current_expiry >= today` and member is active, adds `term->duration_months` directly to `current_expiry`. Zero days of remaining validity are lost!
  - **Late Renewal Math:** If `current_expiry < today` or member is inactive, sets `start_date = today` and `expiry_date = today + term->duration_months`, resetting member status to `active`.
  - **Post-Renewal Paid Invoice (DEC-03):** Automatically generates a paid `Invoice` record (`payment_method = 'bkash_manual'`, `payment_reference = $bkashTxid`, `status = 'paid'`, `amount_bdt = $term->fee_bdt`, `invoice_number = INV-{YEAR}-{MEMBER_SEQ_5}-R`).
  - Emits immutable `AuditLog` entry with `event_type = member_renewal_approved`.
  - Triggers workflow rule `WorkflowRule::TRIGGER_REQUEST_OUTCOME`.

### 2. Portal Renewal Modal & Profile Validity Card (`SubmitRenewalModal`, `MemberPortalProfile`)
- Mounted `SubmitRenewalModal` in `member-portal-profile.blade.php`.
- Profile validity card displays real-time renewal status:
  - If renewal window is open: renders active "Renew Membership" button.
  - If renewal window is closed: renders informational status with exact date renewal opens ("Renewal opens 30 days prior ([Date])").
- Modal presents:
  - Radio list of active workspace membership terms with durations and fees.
  - Step-by-step bKash merchant payment instructions with member ID reference.
  - Real-time uppercase-transformed 10-character bKash Transaction ID input.
  - Gated submit button disabled when window is closed or request is pending.

### 3. CRM Change Request Directory Enhancements (`MemberChangeRequestDirectory`)
- Highlights renewal change requests with distinct Blue badge: "Membership Renewal".
- Review modal incorporates a dedicated **Membership Renewal Payment Verification** section displaying:
  - Selected Membership Term & Fee Amount (BDT).
  - bKash Transaction ID in highlighted monospace font.
  - Current Expiry Date and Member Status.
  - Projected New Expiry Date computed in real-time according to Early vs Late calculation rules.
- Approve action dispatches `MemberRenewalService::approveRenewal`, updates member validity, issues paid invoice, and flashes confirmation status.

### 4. Holistic Phase 9 Integration Test Suite (`EndToEndMemberPortalTest`)
Exercises complete sequential user journeys covering all 5 Phase 9 PORT requirements:
- **Scenario 1 (PORT-01):** Dual-identifier authentication (Member ID and Email), first-login password reset interception, valid password submission, flag clearance, direct profile access, logout, and credential persistence.
- **Scenario 2 (PORT-02):** Read-only personal profile presentation, strict exclusion of staff verification notes, internal audit logs, and conversion invoices, private document streaming with Content-Disposition: inline, and IDOR defense blocking cross-member downloads with HTTP 403.
- **Scenario 3 (PORT-03):** Profile demographic update request submission, pending duplicate prevention, manager diff review in CRM, approval transition, and member record mutation.
- **Scenario 4 (PORT-04):** Dedicated email change workflow, manager alert email dispatch, manager approval with TOCTOU race condition guard, atomic synchronization across `members` and `users` tables, and immediate login with new credentials.
- **Scenario 5 (PORT-05):** 30-day temporal window check, bKash TxID regex and uniqueness enforcement, early renewal math preserving remaining days, paid invoice issuance, and late renewal math resetting start date to approval date.

## Verification & Test Results

### 1. `tests/Feature/MemberRenewalWorkflowTest.php` (15 passed, 60 assertions):
- `test_renewal_window_eligibility_logic` (PASS)
- `test_active_member_with_more_than_30_days_remaining_cannot_submit_renewal` (PASS)
- `test_active_member_within_30_days_submits_renewal_successfully` (PASS)
- `test_inactive_expired_member_submits_renewal_successfully` (PASS)
- `test_submitting_duplicate_pending_renewal_request_is_blocked` (PASS)
- `test_10_character_uppercase_bkash_txid_regex_is_strictly_enforced` (PASS)
- `test_duplicate_bkash_txid_in_invoices_or_leads_is_rejected` (PASS)
- `test_submit_renewal_modal_renders_and_submits_via_livewire` (PASS)
- `test_submit_renewal_modal_blocks_submission_when_window_closed` (PASS)
- `test_early_renewal_preserves_remaining_validity_without_lost_days` (PASS)
- `test_late_renewal_calculates_validity_from_approval_date_and_resets_status_to_active` (PASS)
- `test_approval_creates_paid_invoice_with_bkash_txid` (PASS)
- `test_approval_creates_immutable_audit_log_entry` (PASS)
- `test_unauthorized_staff_cannot_approve_renewal` (PASS)
- `test_member_change_request_directory_approves_renewal_and_updates_view` (PASS)

### 2. `tests/Feature/EndToEndMemberPortalTest.php` (5 passed, 63 assertions):
- `test_scenario_1_authentication_and_forced_password_reset` (PASS)
- `test_scenario_2_read_only_presentation_and_information_isolation` (PASS)
- `test_scenario_3_profile_change_request_and_staff_approval` (PASS)
- `test_scenario_4_dedicated_email_change_workflow` (PASS)
- `test_scenario_5_renewal_window_and_dynamic_expiry_calculations` (PASS)

### 3. Full Phase 9 Feature Test Suite (75 passed, 381 assertions):
- `PortalAuthenticationAndTracerTest` (14 passed)
- `PortalReadOnlyProfileTest` (11 passed)
- `MemberChangeRequestTest` (15 passed)
- `MemberEmailChangeTest` (15 passed)
- `MemberRenewalWorkflowTest` (15 passed)
- `EndToEndMemberPortalTest` (5 passed)

## Deviations from Plan

None - plan executed exactly as written.

## Threat Model Verification

- **T-09-13 (Financial Fraud / bKash TxID Reuse):** Enforced exact 10-character uppercase alphanumeric regex `/^[A-Z0-9]{10}$/` and system-wide uniqueness querying `leads`, `invoices`, and pending `member_change_requests`. Re-evaluated inside transaction lock in `approveRenewal`. Verified by `test_10_character_uppercase_bkash_txid_regex_is_strictly_enforced` and `test_duplicate_bkash_txid_in_invoices_or_leads_is_rejected`.
- **T-09-14 (Integrity Tampering / Premature Renewal):** 30-day temporal renewal window strictly enforced server-side in `MemberRenewalService::isRenewalWindowOpen` and `submitRenewalRequest`. Submissions with > 30 days remaining are rejected with `DomainException`. Verified by `test_active_member_with_more_than_30_days_remaining_cannot_submit_renewal` and `test_submit_renewal_modal_blocks_submission_when_window_closed`.
- **T-09-15 (Information Drift / Truncated Validity):** Formalized dynamic early vs late renewal math: early renewals preserve 100% of remaining days by appending duration to existing expiry (`new_expiry = current_expiry + duration_months`); late renewals reset start date to approval date (`new_expiry = today + duration_months`). Verified by `test_early_renewal_preserves_remaining_validity_without_lost_days` and `test_late_renewal_calculates_validity_from_approval_date_and_resets_status_to_active`.

## Self-Check: PASSED
- `app/Domain/Audit/Models/AuditLog.php` FOUND
- `app/Domain/Members/Services/MemberRenewalService.php` FOUND
- `app/Domain/Members/Services/MemberChangeRequestService.php` FOUND
- `app/Livewire/Portal/Modals/SubmitRenewalModal.php` FOUND
- `resources/views/livewire/portal/modals/submit-renewal-modal.blade.php` FOUND
- `resources/views/livewire/portal/member-portal-profile.blade.php` FOUND
- `app/Livewire/Members/MemberChangeRequestDirectory.php` FOUND
- `resources/views/livewire/members/member-change-request-directory.blade.php` FOUND
- `tests/Feature/MemberRenewalWorkflowTest.php` FOUND
- `tests/Feature/EndToEndMemberPortalTest.php` FOUND
- Commit `da8a6a9` (Task 1) verified in git history
- Commit `1dfc858` (Task 2) verified in git history
- Commit `b5d4642` (Task 3) verified in git history
