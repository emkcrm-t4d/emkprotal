---
phase: 06-leads-verification-and-controlled-conversion
plan: 05
subsystem: leads
tags: [leads, conversion, modal, livewire, invariant-gate, rbac, membership-terms]

requires:
  - phase: 06-leads-verification-and-controlled-conversion
    plan: 04
    provides: Atomic 10-step conversion engine, monotonic Member ID generator, and invariant exception

provides:
  - Managerial Lead Conversion modal component (ConvertLeadModal)
  - Real-time Tri-State invariant UI defense on Lead Profile
  - Dynamic membership term selector displaying database-configured duration and fee options per DEC-08
  - Multi-layer authorization guards and tooltips blocking unauthorized staff from conversion triggers
  - Converted member status badge linking directly to member records upon successful conversion

affects:
  - 06-06-PLAN.md
  - Phase 07 (Members & Cards)
  - Phase 08 (Member Portal)

actuals:
  tokens: 11095
  tasks: 1
  commits: 1
  plan_head_before: fb566d49ca71a93bb3983a4794e772f4e85d4bb3
  plan_head_after: ebaa287986d19653322e9bfd1844405b14c29c71

tech-stack:
  added: []
  patterns:
    - "Multi-layer invariant defense combining presentation layer tooltips, Livewire action guards, and domain service exceptions"
    - "Dynamic term fee and duration calculations from database-configured membership_terms per DEC-08"
    - "Restricted Workspace Manager authority guard with HTTP 403 abort on delegated staff attempts per DEC-06 and DELEGATION-MATRIX.md"

key-files:
  created:
    - app/Livewire/Leads/Modals/ConvertLeadModal.php
    - resources/views/livewire/leads/modals/convert-lead-modal.blade.php
    - tests/Feature/LeadConversionModalTest.php
  modified:
    - app/Domain/Leads/Models/Lead.php
    - app/Livewire/Leads/LeadProfile.php
    - resources/views/livewire/leads/lead-profile.blade.php

key-decisions:
  - "Enforced strict UI-level and action-level gating blocking 'Convert to Member' action unless Payment, Document, and Photo streams are verified and lead is approved per DEC-03"
  - "Restricted conversion trigger exclusively to Workspace Managers possessing leads.convert, rejecting delegated staff with HTTP 403 per DELEGATION-MATRIX.md"
  - "Provided dynamic membership term selection pulling fee (BDT) and validity period from active membership_terms without hardcoded durations per DEC-08"
  - "Rendered converted state with purple badge displaying Member ID and linking to members module once conversion transaction succeeds"

patterns-established:
  - "Computed isConversionAllowed property on LeadProfile evaluating payment_verification_status, document_verification_status, photo_verification_status, and overall approved status"
  - "ConvertLeadModal enforcing both Spatie permission leads.convert and LeadConversionService invariant verification before initiating database transaction"
  - "Converted status badge displaying composite Member ID ({SPACE}-{YEAR}-{SEQ}) with direct navigation link to workspace members module"

requirements-completed:
  - LEAD-05

coverage:
  - id: C1
    description: "Convert to Member UI gating disables button when any stream is unverified and enables only when all 3 streams are verified and approved"
    requirement: LEAD-05
    verification:
      - kind: integration
        ref: "tests/Feature/LeadConversionModalTest.php"
        status: pass
    human_judgment: false
  - id: C2
    description: "Authorization defense prevents delegated staff from opening conversion modal or triggering conversion"
    requirement: LEAD-05
    verification:
      - kind: integration
        ref: "tests/Feature/LeadConversionModalTest.php"
        status: pass
    human_judgment: false
  - id: C3
    description: "Workspace Manager can select active membership terms, preview fees and dates, and execute atomic conversion transaction"
    requirement: LEAD-05
    verification:
      - kind: integration
        ref: "tests/Feature/LeadConversionModalTest.php"
        status: pass
    human_judgment: false
  - id: C4
    description: "Profile view updates to reflect converted status and displays purple Member ID badge after conversion"
    requirement: LEAD-05
    verification:
      - kind: integration
        ref: "tests/Feature/LeadConversionModalTest.php"
        status: pass
    human_judgment: false

duration: 8min
completed: 2026-11-04
status: complete
---

# Phase 06 Plan 05: Manager Conversion Modal, Invariant Gate UI Defense, and Term Selection Summary

**Managerial conversion modal interface with dynamic database term selection, multi-layer authorization defense (leads.convert), and presentation-layer invariant gating asserting complete Tri-State verification prior to Member ID allocation.**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-11-04T17:37:00Z
- **Completed:** 2026-11-04T17:45:00Z
- **Tasks:** 1
- **Files modified:** 6

## Accomplishments

- Implemented `ConvertLeadModal` Livewire component (`app/Livewire/Leads/Modals/ConvertLeadModal.php`) featuring:
  - Workspace tenancy hydration protection (`ValidatesWorkspaceOnHydration`).
  - Spatie RBAC authorization check asserting `leads.convert` permission on both modal mount and conversion execution (aborting with HTTP 403 on delegated staff attempts per T-06-09).
  - Invariant validation asserting all 3 streams (Payment, Document, Photo) are verified and overall status is approved before executing conversion (mitigating T-06-10).
  - Term selection validation pulling active database `MembershipTerm` records dynamically per DEC-08.
  - Event dispatching (`conversionCompleted`, `leadUpdated`, `lead-updated`) and session flash notification upon successful conversion.
- Designed `resources/views/livewire/leads/modals/convert-lead-modal.blade.php`:
  - Emerald-themed managerial confirmation dialog.
  - Tri-State verification summary checklist displaying stream verification statuses and bKash TxID.
  - Dynamic Membership Term selector dropdown showing validity months and fee in BDT.
  - Selected term preview card calculating dynamic validity start and expiry dates.
  - Applicant summary and conversion warning regarding atomic Member ID provisioning, invoice creation, badge queueing, and portal credentials.
  - Action buttons: "Confirm & Convert to Member" (emerald with loading state) and "Cancel".
- Updated `app/Livewire/Leads/LeadProfile.php`:
  - Added computed property `isConversionAllowed` checking all 3 verification streams are `verified` and overall lead status is `approved`.
  - Added `openConversionModal()` action checking `leads.convert` authority and dispatching `openConvertLeadModal`.
  - Added `handleConversionCompleted()` event listener updating lead state and flashing success notification.
  - Eager loaded `convertedMember` relation on mount and refresh.
- Updated `resources/views/livewire/leads/lead-profile.blade.php`:
  - Added "Convert to Member" action in staff toolbar with multi-tier gating:
    - Disabled button with tooltip `"Conversion requires Workspace Manager authority."` for staff lacking `leads.convert`.
    - Disabled button with tooltip `"All 3 verifications (Payment, Document, Photo) and Manager Approval must be verified to convert."` when conversion invariant is unmet.
    - Active emerald button `"Convert to Member"` when invariant is satisfied and user has managerial authority.
    - Purple converted badge `"Converted to Member: [Member ID]"` with link to workspace members module once conversion is complete.
  - Embedded `@livewire('leads.modals.convert-lead-modal', ...)` into the profile view.
- Added `convertedMember(): BelongsTo` relationship on `Lead` model (`app/Domain/Leads/Models/Lead.php`).
- Created and executed comprehensive feature test suite `tests/Feature/LeadConversionModalTest.php`:
  - Verified disabled button state and invariant tooltip when streams are incomplete.
  - Verified enabled button state when all 3 streams are verified and lead is approved.
  - Verified delegated staff are rejected with HTTP 403 on openConversionModal, openModal, and executeConversion.
  - Verified Workspace Manager can open modal, select term, and execute conversion.
  - Verified profile displays purple converted badge with Member ID link post-conversion.
  - Verified modal rejects premature conversion attempts even if invoked directly.
- Verified 6 passed tests (36 assertions) for Plan 06-05, and verified 25 regression tests (129 assertions) with 0 failures.

## Task Commits

1. **Task 1: Manager Conversion Modal, Invariant Gate UI Defense, and Term Selection** - `ebaa287` (feat)

## Files Created/Modified

- `app/Domain/Leads/Models/Lead.php` - Added `convertedMember(): BelongsTo` relation linking to `Member`.
- `app/Livewire/Leads/LeadProfile.php` - Added `isConversionAllowed` computed property, `openConversionModal()` action, and `handleConversionCompleted()` event listener.
- `resources/views/livewire/leads/lead-profile.blade.php` - Added toolbar conversion button with invariant tooltips, converted status badge, and modal inclusion.
- `app/Livewire/Leads/Modals/ConvertLeadModal.php` - Livewire component managing term selection, invariant assertion, and atomic conversion invocation.
- `resources/views/livewire/leads/modals/convert-lead-modal.blade.php` - Emerald modal view with Tri-State badges, term dropdown, applicant summary, and warning.
- `tests/Feature/LeadConversionModalTest.php` - Feature test suite asserting UI states, RBAC enforcement, term selection, and conversion execution.

## Decisions Made

- Enforced strict presentation-layer and component-layer invariant gating preventing conversion button interactions until all 3 verification streams are completed and lead is approved per DEC-03.
- Reserved conversion execution exclusively to Workspace Managers (`leads.convert`), rejecting delegated staff attempts with HTTP 403 per DELEGATION-MATRIX.md.
- Sourced duration and fee options dynamically from database-configured `membership_terms` without hardcoded durations per DEC-08.
- Displayed persistent purple badge with generated Member ID (`EMK-{YEAR}-{SEQ}`) and members link upon successful lead conversion.

## Deviations from Plan

None - plan executed exactly as written.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 06-05 complete with manager conversion modal and UI-level invariant defenses fully verified.
- Ready for Plan 06-06: Controlled 30-Day Conversion Reversal, State Machine Reset, and Immutable Audit Logging.
- No blockers.

## Self-Check: PASSED

- All 3 created files exist on disk: FOUND
- Commit hash `ebaa287` exists in git history: FOUND
- Test suites pass with 6 passed tests and 36 assertions for Plan 06-05: FOUND
- Regression test suites pass with 25 passed tests and 129 assertions: FOUND

---
*Phase: 06-leads-verification-and-controlled-conversion*
*Completed: 2026-11-04*
