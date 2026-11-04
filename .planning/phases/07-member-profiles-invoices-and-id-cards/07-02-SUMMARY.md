---
phase: 07-member-profiles-invoices-and-id-cards
plan: 02
status: complete
subsystem: crm-members
tags:
  - validity-engine
  - membership-terms
  - livewire
  - tenancy
  - audit-logs
requires:
  - 07-01
provides:
  - membership-validity-engine
  - manage-terms-ui
affects:
  - 07-03
  - 07-04
  - 07-05
tech-stack:
  added: []
  patterns:
    - Carbon addMonthsNoOverflow calendar integrity
    - Layer 2 ValidatesWorkspaceOnHydration
    - TenantScope BelongsToTenant scoping
    - Spatie can:platform_services.manage_settings authorization
    - Immutable audit trail recording
key-files:
  created:
    - app/Domain/Members/Services/MembershipValidityEngine.php
    - app/Livewire/Members/ManageTerms.php
    - resources/views/livewire/members/manage-terms.blade.php
    - tests/Unit/MembershipValidityEngineTest.php
    - tests/Feature/MembershipTermManagementTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Forms/Models/MembershipTerm.php
    - routes/web.php
decisions:
  - Used addMonthsNoOverflow to prevent calendar month overflow on leap years and varying month lengths
  - Enforced platform_services.manage_settings authorization as non-delegable managerial action on /crm/{workspace}/settings/terms
  - Locked term code and duration_months from mutation if members are assigned to preserve historical date calculations
  - Recorded membership_term_created and membership_term_updated immutable audit events per DEC-11
metrics:
  duration: 14m
  completed_date: "2026-11-04"
actuals:
  tokens: 11500
  tasks: 2
  commits: 2
  plan_head_before: "0a320725100512dab586d1141c2ddbebd8e6c9a9"
  plan_head_after: "d74e6e32bc6f7987635b23ff31707a52376fbf8f"
---

# Phase 07 Plan 02: Dynamic Validity Engine and Workspace Membership Terms Management Summary

**One-liner:** Dynamic membership validity engine supporting day-preserving early renewals and late approval resets, paired with workspace-isolated Livewire terms management, tamper-proof historical locking, and append-only audit logging.

## Executive Summary

Plan 07-02 delivered the Dynamic Validity Engine and Workspace Membership Term Management subsystem per MEMB-02, DEC-08, STATUS-DICTIONARY.md (Section 7), and MODULE-REGISTRY.md:
1. **Dynamic Membership Validity Engine (`MembershipValidityEngine`):** Dedicated domain service in `App\Domain\Members\Services` calculating start and expiration dates without hardcoded assumptions. Supports:
   - Initial member induction start/expiry calculation defaulting to manager approval timestamp (`now()`) and term duration.
   - Early renewals (during or prior to the 30-day pre-expiry window): appends term duration directly onto the existing expiration date without loss of remaining membership days per STATUS-DICTIONARY Section 7.2.
   - Late renewals (post-expiration): resets the start date to the renewal approval timestamp and computes new expiry from that date.
   - 30-day renewal window detection and expiration boundary checks.
   - Carbon `addMonthsNoOverflow` calendar integrity handling leap years and differing month lengths.
2. **Workspace Terms & Fee Management UI (`ManageTerms`):** Managerial Livewire component registered at `/crm/{workspace}/settings/terms` guarded by `workspace.access` middleware and `can:platform_services.manage_settings` authorization (strictly non-delegable managerial action per MODULE-REGISTRY.md Section 5.2):
   - Table view showing workspace terms with duration, fee in BDT, active/inactive badge, member usage count, and actions.
   - Modal dialog for creating terms with unique workspace-scoped codes, custom durations (1-60 months), and pricing.
   - Tamper-proofing (T-07-05): Automatically locks `code` and `duration_months` from mutation once members are assigned to the term to protect historical calculation integrity.
   - Active/inactive status toggle for retiring terms without breaking historical member records.
   - Immutable audit logging: Emits `membership_term_created` and `membership_term_updated` events with JSON snapshots and actor attribution into `audit_logs` per DEC-11.

## Tasks Executed

### Task 1: Core MembershipValidityEngine Domain Service for Dynamic Dates and Early/Late Renewals
- Added `members()` relationship (`HasMany`) on `MembershipTerm` model.
- Implemented `App\Domain\Members\Services\MembershipValidityEngine` with `calculateInitialDates()`, `calculateRenewalDates()`, `isWithinRenewalWindow()`, and `isExpired()`.
- Implemented unit tests in `tests/Unit/MembershipValidityEngineTest.php` covering initial induction, early renewals preserving remaining days, exact-day renewal, late renewals post-expiration, 30-day window boundaries, expiration checks, and leap year handling.
- **Commit:** `668fcee` (`feat(07-02): implement membership validity engine and dynamic date calculation`).

### Task 2: Dynamic Workspace Terms & Fee Management Livewire Component
- Registered route `/crm/{workspace}/settings/terms` pointing to `\App\Livewire\Members\ManageTerms::class` with `can:platform_services.manage_settings`.
- Added audit event constants `EVENT_MEMBERSHIP_TERM_CREATED` and `EVENT_MEMBERSHIP_TERM_UPDATED` to `AuditLog`.
- Implemented `App\Livewire\Members\ManageTerms` component with `ValidatesWorkspaceOnHydration`, multi-level authorization, validation rules, locked field defenses, and audit logging.
- Created Tailwind Blade view `resources/views/livewire/members/manage-terms.blade.php` with responsive table, modal dialog, and locked field warnings.
- Created feature test `tests/Feature/MembershipTermManagementTest.php` testing unauthenticated redirect, 403 for non-managers, 200 for managers, cross-workspace 403, term creation, code uniqueness per workspace, regex validation, term editing, member assignment locking, and status toggles.
- **Commit:** `d74e6e3` (`feat(07-02): implement workspace membership terms management UI and permission guards`).

## Verification Results

Full automated test suite executed for Phase 7 Wave 2:
```
php artisan test tests/Unit/MembershipValidityEngineTest.php tests/Feature/MembershipTermManagementTest.php
```
Output:
```
PASS  Tests\Unit\MembershipValidityEngineTest
✓ calculate initial dates defaults start date to now                                                           0.34s  
✓ calculate initial dates with various term durations                                                          0.02s  
✓ early renewal preserves remaining days and extends expiry                                                    0.05s  
✓ early renewal on exact expiry date                                                                           0.03s  
✓ late renewal post expiration resets start date to approval date                                              0.03s  
✓ renewal window boundaries                                                                                    0.02s  
✓ is expired detection logic                                                                                   0.02s  
✓ leap year handling                                                                                           0.02s  

PASS  Tests\Feature\MembershipTermManagementTest
✓ unauthenticated requests redirect to login                                                                   0.37s  
✓ non managerial staff receive 403 forbidden                                                                   0.19s  
✓ workspace manager can view terms index route                                                                 0.23s  
✓ cross workspace manager receives 403                                                                         0.17s  
✓ workspace manager can create new term                                                                        0.28s  
✓ term code uniqueness within workspace                                                                        0.29s  
✓ term code format validation                                                                                  0.21s  
✓ manager can edit term without members                                                                        0.24s  
✓ term with active members locks code and duration from mutation                                               0.24s  
✓ toggle term active status                                                                                    0.22s  

Tests: 18 passed (60 assertions)
Duration: 3.24s
```

Regression test check across previous Wave 1 components:
```
php artisan test tests/Feature/MemberDirectoryTest.php tests/Feature/MemberProfileTest.php
```
Output:
```
Tests: 14 passed (97 assertions)
Duration: 5.85s
```

## Deviations from Plan

### Auto-fixed Issues
1. **[Rule 1 - Bug] Used Carbon addMonthsNoOverflow to prevent calendar month overflow**
   - **Found during:** Task 1 unit test execution
   - **Issue:** Standard `addMonths(6)` on `2026-12-31` rolls into `2027-07-01` because June only has 30 days; `addMonths(12)` on leap day `2024-02-29` rolls into `2025-03-01`.
   - **Fix:** Switched to Carbon's `addMonthsNoOverflow()`, correctly clamping month boundaries (`2027-06-30` and `2025-02-28`).
   - **Files modified:** `app/Domain/Members/Services/MembershipValidityEngine.php`
   - **Commit:** `668fcee`

2. **[Rule 2 - Auto-add] Added EVENT_MEMBERSHIP_TERM_CREATED and EVENT_MEMBERSHIP_TERM_UPDATED constants**
   - **Found during:** Task 2 implementation
   - **Issue:** Explicit event type tokens for term lifecycle logging were missing from `AuditLog`.
   - **Fix:** Added constants `EVENT_MEMBERSHIP_TERM_CREATED = 'membership_term_created'` and `EVENT_MEMBERSHIP_TERM_UPDATED = 'membership_term_updated'`.
   - **Files modified:** `app/Domain/Audit/Models/AuditLog.php`
   - **Commit:** `d74e6e3`

## Known Stubs
None - all date calculations, database relationships, Livewire interactions, and audit logging pathways are fully wired and verified.

## Threat Surface Verification
- **T-07-04 (Elevation of Privilege):** `/crm/{workspace}/settings/terms` is strictly restricted to Workspace Managers via route middleware and Livewire component authorization using `can:platform_services.manage_settings`. Regular staff receive HTTP 403.
- **T-07-05 (Tampering):** Once a term is assigned to members (`members()->exists()`), `ManageTerms::updateTerm` prevents mutation of `code` and `duration_months`, maintaining historical integrity for existing members.
- **T-07-06 (Business Logic Error):** Unit tests verified that early renewals append duration directly to existing expiration dates, ensuring zero loss of remaining membership days.

## Self-Check: PASSED
- [x] `app/Domain/Members/Services/MembershipValidityEngine.php` exists
- [x] `app/Livewire/Members/ManageTerms.php` exists
- [x] `resources/views/livewire/members/manage-terms.blade.php` exists
- [x] `tests/Unit/MembershipValidityEngineTest.php` exists
- [x] `tests/Feature/MembershipTermManagementTest.php` exists
- [x] Commit `668fcee` verified in git history
- [x] Commit `d74e6e3` verified in git history
