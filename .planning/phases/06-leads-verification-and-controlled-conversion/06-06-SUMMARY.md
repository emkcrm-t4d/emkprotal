---
phase: 06-leads-verification-and-controlled-conversion
plan: 06
subsystem: members
tags: [reversal, rollback, audit, lead-lifecycle, e2e, integration-tests, rbac]

requires:
  - phase: 06-leads-verification-and-controlled-conversion
    plan: 05
    provides: Managerial conversion modal, UI-level invariant defense, and dynamic term selection

provides:
  - 30-Day Conversion Reversal domain service (ConversionReversalService)
  - Reversal validation exception (InvalidConversionReversalException) returning HTTP 422
  - Managerial Reversal Modal with mandatory 20+ char justification and administrative password challenge
  - Cascading entity rollback (member archived_reversed, portal user deactivated, invoice void_reversed, card voided_reversal, lead restored to under_review)
  - Holistic Phase 6 End-to-End integration test suite verifying LEAD-01 through LEAD-07 across 5 complete operational lifecycle scenarios

affects:
  - Phase 07 (Members & Cards)
  - Phase 08 (Member Portal)

actuals:
  tokens: 24083
  tasks: 3
  commits: 3
  plan_head_before: 315db7322326b9b9cfb09bee65ddaaa4a8fef161
  plan_head_after: b7dc95732380cdd8959ea14ba90bc4a831d4e1a4

tech-stack:
  added: []
  patterns:
    - "Time-bounded statutory cutoff guard rejecting entity rollback attempts after 30 calendar days per DEC-04"
    - "Cascading transactional rollback across 5 relational entities asserting state purity (no subsequent invoices, card not delivered) per CONVERSION-SPEC.md Section 6"
    - "Administrative re-authentication challenge verifying manager password via Hash::check prior to destructive rollback"
    - "Non-delegable Spatie RBAC permission leads.reverse_conversion restricted strictly to Workspace Managers and Superadmins per DELEGATION-MATRIX.md"

key-files:
  created:
    - app/Domain/Members/Exceptions/InvalidConversionReversalException.php
    - app/Domain/Members/Services/ConversionReversalService.php
    - app/Livewire/Members/Modals/ConversionReversalModal.php
    - resources/views/livewire/members/modals/conversion-reversal-modal.blade.php
    - tests/Feature/ConversionReversalServiceTest.php
    - tests/Feature/ConversionReversalModalTest.php
    - tests/Feature/EndToEndLeadVerificationAndConversionTest.php
  modified:
    - app/Livewire/Leads/LeadProfile.php
    - resources/views/livewire/leads/lead-profile.blade.php

key-decisions:
  - "Enforced 30-calendar-day statutory cutoff from member created_at timestamp; reversals beyond 30 days are permanently rejected per DEC-04"
  - "Enforced minimum 20-character mandatory justification string and administrative password confirmation before initiating rollback per CONVERSION-SPEC.md"
  - "Executed cascading transactional rollback transitioning member to archived_reversed, portal user to inactive with remember_token cleared, invoice to void_reversed, card to voided_reversal, and restoring lead to under_review while strictly preserving lead verification history and attachments"
  - "Restricted conversion reversal authorization strictly to Workspace Managers (leads.reverse_conversion); non-delegable to delegated staff per DELEGATION-MATRIX.md"
  - "Emitted immutable conversion_reversed audit event in audit_logs capturing manager ID, justification reason, and state snapshots per DEC-11"

patterns-established:
  - "canReverse() evaluation method inspecting statutory cutoff, member active status, subsequent invoices, and physical card delivery lock before presenting action"
  - "Pessimistic lockForUpdate() on member row inside DB::transaction to prevent concurrent mutation during reversal"
  - "Reversal button conditionally displayed on converted leads within 30 days exclusively to managers with leads.reverse_conversion authority"

requirements-completed:
  - LEAD-06

duration: 25min
completed: 2026-11-04
status: complete
---

# Phase 06 Plan 06: 30-Day Conversion Reversal Protocol and Holistic Phase 6 E2E Integration Suite Summary

**Controlled 30-day conversion reversal domain service with cascading entity rollback, managerial re-authentication modal with 20-character justification enforcement, and a comprehensive end-to-end integration test suite validating all seven lead lifecycle requirements (LEAD-01 through LEAD-07).**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-11-04T17:51:00Z
- **Completed:** 2026-11-04T18:10:00Z
- **Tasks:** 3
- **Files modified:** 9

## Accomplishments

- Implemented `InvalidConversionReversalException` (`app/Domain/Members/Exceptions/InvalidConversionReversalException.php`) returning HTTP 422 with structured unmet reasons array.
- Built `ConversionReversalService` domain engine (`app/Domain/Members/Services/ConversionReversalService.php`) featuring:
  - Statutory 30-calendar-day cutoff guard (`STATUTORY_CUTOFF_DAYS = 30`) per DEC-04.
  - Mandatory justification guard (`MIN_JUSTIFICATION_CHARS = 20`).
  - Status purity guard asserting member is in `active` state.
  - Subsequent activity guard asserting zero subsequent invoices or renewals exist.
  - Card delivery lock asserting physical ID card was not marked `delivered`.
  - Cascading transactional rollback:
    - Member status transitions to `archived_reversed`.
    - Portal user deactivated (`is_active = false`, `remember_token = null`).
    - Post-conversion invoice marked `void_reversed`.
    - Plastic ID card marked `voided_reversal`.
    - Originating Lead restored to `under_review`, `converted_member_id = null`, preserving verification streams and uploaded files.
    - Immutable audit event `conversion_reversed` recorded in `audit_logs` with actor ID, reason, and state snapshots per DEC-11.
- Implemented `ConversionReversalModal` Livewire component (`app/Livewire/Members/Modals/ConversionReversalModal.php`) and Blade view (`resources/views/livewire/members/modals/conversion-reversal-modal.blade.php`):
  - Rose/amber warning theme with warning alert explaining destructive rollback.
  - Mandatory justification textarea with live character counter (`Characters: X/20 (min 20)`).
  - Administrative password confirmation input challenging the manager's password via `Hash::check`.
  - Strict Spatie authorization check asserting `leads.reverse_conversion` permission on mount and execution.
- Updated `LeadProfile` (`app/Livewire/Leads/LeadProfile.php`) and view (`resources/views/livewire/leads/lead-profile.blade.php`):
  - Added `isReversalAllowed` computed property calling `ConversionReversalService::canReverse`.
  - Added `openReversalModal()` action dispatched via `#openConversionReversalModal`.
  - Added `handleConversionReversed()` event listener refreshing lead profile and flashing success notice.
  - Rendered "Reverse Conversion" button alongside converted member badge when eligible and authorized.
  - Embedded `conversion-reversal-modal` component into profile view.
- Created `ConversionReversalServiceTest` (`tests/Feature/ConversionReversalServiceTest.php`) verifying all 9 domain scenarios (46 assertions).
- Created `ConversionReversalModalTest` (`tests/Feature/ConversionReversalModalTest.php`) verifying UI button appearance, staff rejection (403), validation, password challenge, and rollback execution (27 assertions).
- Created holistic `EndToEndLeadVerificationAndConversionTest` (`tests/Feature/EndToEndLeadVerificationAndConversionTest.php`) validating LEAD-01 through LEAD-07 across 5 operational lifecycle scenarios (64 assertions):
  - Scenario 1 (LEAD-01 & Tenancy Defense): Directory search, multi-facet filtering by name/bKash, and cross-workspace 403 isolation.
  - Scenario 2 (LEAD-02 & LEAD-03 Tri-State Verifications): DEC-09 minor consent document verification, 5-point studio photo checklist, staff payment rejection, and manager bKash verification.
  - Scenario 3 (LEAD-04 & LEAD-07 Missing Items, Rejection, and Blocking): Missing items mail notification, rejection taxonomy codes, clean reapplication history detection, and abusive email blocking.
  - Scenario 4 (LEAD-05, ARCH-03, DEC-03 Atomic Conversion): Conversion invariant gate, monotonic Member ID allocation (`EMK-2026-00001`), dynamic date computation (DEC-08), paid invoice, badge queue, portal user account, and immutable audit log.
  - Scenario 5 (LEAD-06, ARCH-04, DEC-04 Conversion Reversal): Staff rejection (403), short justification rejection, manager reversal with password challenge and cascading rollback, Day 31 cutoff, and card delivery lock.
- Executed full Phase 6 test suite across 15 test classes: 94 passed, 529 assertions, 0 failures.

## Task Commits

1. **Task 1: 30-Day Conversion Reversal Domain Engine with Cascading Rollback** - `ac1a808` (feat)
2. **Task 2: Manager Reversal Modal with Password Confirmation and Justification Enforcement** - `2bb733c` (feat)
3. **Task 3: Holistic Phase 6 End-to-End Integration Test Suite** - `b7dc957` (test)

## Files Created/Modified

- `app/Domain/Members/Exceptions/InvalidConversionReversalException.php` - Custom runtime exception returning HTTP 422 with structured unmet reasons.
- `app/Domain/Members/Services/ConversionReversalService.php` - Domain service enforcing statutory 30-day cutoff, 20-char justification, purity guards, and cascading rollback.
- `app/Livewire/Members/Modals/ConversionReversalModal.php` - Livewire component with password re-authentication, validation, and reversal action.
- `resources/views/livewire/members/modals/conversion-reversal-modal.blade.php` - Rose/amber modal view with live character counter and warning alert.
- `app/Livewire/Leads/LeadProfile.php` - Added `isReversalAllowed` computed property, `openReversalModal` action, and `conversionReversed` listener.
- `resources/views/livewire/leads/lead-profile.blade.php` - Added "Reverse Conversion" button and embedded reversal modal component.
- `tests/Feature/ConversionReversalServiceTest.php` - Feature tests verifying domain guards, statutory cutoff, and cascading rollback.
- `tests/Feature/ConversionReversalModalTest.php` - Feature tests verifying UI rendering, RBAC authorization, password challenge, and execution.
- `tests/Feature/EndToEndLeadVerificationAndConversionTest.php` - Comprehensive E2E test suite covering LEAD-01 through LEAD-07 across 5 scenarios.

## Decisions Made

- Enforced a hard 30-calendar-day statutory cutoff from member creation (`Carbon::parse($member->created_at)->diffInDays(now()) <= 30`), rejecting reversal attempts thereafter per DEC-04.
- Mandated a minimum 20-character justification string and administrative password re-authentication via `Hash::check` before executing entity rollback per CONVERSION-SPEC.md.
- Implemented cascading rollback across all child and parent entities: member transitions to `archived_reversed`, portal credentials are deactivated with `remember_token` evicted, invoice is marked `void_reversed`, card is marked `voided_reversal`, and lead is restored to `under_review` with historical verification streams strictly preserved for staff re-inspection.
- Restricted conversion reversal permissions strictly to Workspace Managers (`leads.reverse_conversion`), rejecting delegated staff attempts with HTTP 403 per DELEGATION-MATRIX.md.
- Recorded immutable audit log entries for `conversion_reversed` capturing the actor ID, reason, and entity state snapshots per DEC-11.

## Deviations from Plan

None - plan executed exactly as written.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 6 (Leads, Verification, and Controlled Conversion) is 100% complete with all 6 plans executed and verified.
- All 7 lead management requirements (LEAD-01, LEAD-02, LEAD-03, LEAD-04, LEAD-05, LEAD-06, LEAD-07) are satisfied.
- Ready for Phase 07: Member Profiles, Invoices, and ID Cards.
- No blockers.

## Self-Check: PASSED

- All 7 created files exist on disk: FOUND
  - `app/Domain/Members/Exceptions/InvalidConversionReversalException.php`: FOUND
  - `app/Domain/Members/Services/ConversionReversalService.php`: FOUND
  - `app/Livewire/Members/Modals/ConversionReversalModal.php`: FOUND
  - `resources/views/livewire/members/modals/conversion-reversal-modal.blade.php`: FOUND
  - `tests/Feature/ConversionReversalServiceTest.php`: FOUND
  - `tests/Feature/ConversionReversalModalTest.php`: FOUND
  - `tests/Feature/EndToEndLeadVerificationAndConversionTest.php`: FOUND
- All 3 commits exist in git history: FOUND
  - `ac1a808`: FOUND
  - `2bb733c`: FOUND
  - `b7dc957`: FOUND
- Full Phase 6 test suite passes with 94 passed tests and 529 assertions: FOUND

---
*Phase: 06-leads-verification-and-controlled-conversion*
*Completed: 2026-11-04*
