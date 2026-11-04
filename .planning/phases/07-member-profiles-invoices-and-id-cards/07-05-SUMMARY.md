---
phase: 07-member-profiles-invoices-and-id-cards
plan: 05
status: complete
subsystem: crm-members-governance
tags: [member-status, id-card-replacement, audit-logs, livewire, e2e-lifecycle]
requires: [07-01, 07-02, 07-03, 07-04]
provides: [member-status-governance, id-card-replacement-workflow, phase-7-e2e-suite]
affects: []
key-files:
  created:
    - app/Domain/Members/Services/MemberStatusService.php
    - app/Livewire/Members/MemberStatusModal.php
    - resources/views/livewire/members/member-status-modal.blade.php
    - app/Livewire/Members/RequestCardReplacementModal.php
    - resources/views/livewire/members/request-card-replacement-modal.blade.php
    - tests/Feature/MemberStatusManagementTest.php
    - tests/Feature/IdCardReplacementTest.php
    - tests/Feature/EndToEndMemberLifecycleTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/IdCards/Services/IdCardLifecycleService.php
    - app/Domain/Invoices/Services/InvoiceService.php
    - app/Livewire/Members/MemberProfile.php
    - resources/views/livewire/members/member-profile.blade.php
    - tests/Feature/VerifyWorkspaceAccessTest.php
commits: 3
plan_head_before: 21dc9898f64ad10cf5807782ea5eda9d8ac3debc
plan_head_after: 0124d05
metrics:
  completed: 2026-11-04
  tests: 20 passed in 07-05 (104 assertions); 420 passed full suite (2337 assertions)
---

# Phase 7 Plan 05: Member Status Governance, ID Card Replacement, and Holistic Lifecycle Suite Summary

Admin-governed member status transitions (`MemberStatusService` + `MemberStatusModal`), physical ID card replacement workflow (`IdCardLifecycleService::replaceCard` + `RequestCardReplacementModal`), and a 6-scenario end-to-end integration test suite validating all Phase 7 requirements (`MEMB-01` through `MEMB-06`).

## Tasks

| Task | Name | Commit |
|------|------|--------|
| 1 | Administrative Member Status Governance Engine and Livewire Status Modal | `b9b6b96` |
| 2 | ID Card Replacement Workflow and Livewire Replacement Modal | `858d6f7` |
| 3 | Holistic Phase 7 End-to-End Integration Test Suite | `0124d05` |

## Key Points
- `MemberStatusService::transitionStatus` enforces non-delegable `members.restrict` / `members.revoke` authority, requires a non-empty management directive reference and 20+ character justification, disables the linked portal `User` (`is_active = false`, `remember_token = null`) upon revocation, and writes an immutable `member_status_changed` audit log entry.
- `IdCardLifecycleService::replaceCard` validates replacement reasons (`LOST_CARD`, `DAMAGED_CARD`, `NAME_CHANGE`, `OTHER`), supersedes active cards to `superseded_replaced`, generates a replacement card with incremented sequence in `provided_for_printing`, optionally bills a replacement fee invoice with a unique 10-character bKash TxID, and logs `id_card_replaced`.
- `EndToEndMemberLifecycleTest` verifies all 6 operational scenarios across Phase 7: conversion to profile/invoice/card tracer, dynamic terms and early renewal preserving remaining days, ID card batch export/print/pickup mail/delivery, card replacement, status governance & revocation, and cross-workspace 403 isolation.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Updated Phase 4 placeholder route assertions in VerifyWorkspaceAccessTest**
- **Found during:** Task 3 (full test suite regression check)
- **Issue:** `VerifyWorkspaceAccessTest::test_staff_in_assigned_workspace_accessing_baseline_routes_receives_200_ok` called `getJson` on `/crm/ws-emk-dhaka/members` and `/crm/ws-emk-dhaka/invoices` expecting the old Phase 4 JSON stub response, which Phase 7 replaced with Livewire `MemberDirectory` and `InvoiceDirectory` components.
- **Fix:** Updated the test to assert `200 OK` HTML responses on `/members` and `/invoices` while preserving the JSON stub assertion on `/reports`.
- **Files modified:** `tests/Feature/VerifyWorkspaceAccessTest.php`
- **Commit:** `0124d05`

## Known Stubs
None.

## Self-Check: PASSED
All created/modified files exist on disk; commits `b9b6b96`, `858d6f7`, and `0124d05` verified on `main`; 20 plan tests (104 assertions) and 420 total suite tests (2337 assertions) pass with 0 failures.

