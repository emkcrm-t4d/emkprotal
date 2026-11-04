---
phase: 06-leads-verification-and-controlled-conversion
plan: 03
subsystem: leads
tags: [leads, communication, mail, rejection, missing-items, abusive-blocking, livewire, modals]

requires:
  - phase: 06-leads-verification-and-controlled-conversion
    plan: 02
    provides: Tri-State verification engine and VerificationPanel
  - phase: 01-discovery-decisions-and-acceptance-criteria
    provides: Standardized rejection taxonomy (VERIFICATION-DICTIONARY.md) and lead status transitions (STATUS-DICTIONARY.md)

provides:
  - Official queued mailables for missing items and standardized rejections
  - LeadCommunicationService with standardized 13-code taxonomy and audit logging
  - Interactive MissingItemsModal and RejectLeadModal Livewire components embedded on LeadProfile
  - Abusive applicant email blocking guard at submission perimeter preserving clean reapplication

affects:
  - 06-04-PLAN.md
  - 06-05-PLAN.md
  - 06-06-PLAN.md

actuals:
  tokens: 23086
  tasks: 3
  commits: 3
  plan_head_before: e22eb40825eb39d67b2d2f7aebe2bfebc6f6e522
  plan_head_after: 11d8edb29094709d31d794cbdb9a464501a1db91

tech-stack:
  added: []
  patterns:
    - "Standardized rejection taxonomy mapping: 13 reason codes partitioned by stream (payment, document, photo) with customer-facing explanations"
    - "Reactive modal overlays in Livewire: dispatching events, resetting validation, and triggering parent profile refreshes"
    - "Submission perimeter security: blocking flagged abusive emails while keeping legitimate reapplication open"

key-files:
  created:
    - app/Domain/Leads/Mail/MissingItemsNotificationMail.php
    - app/Domain/Leads/Mail/LeadRejectionMail.php
    - app/Domain/Leads/Services/LeadCommunicationService.php
    - resources/views/emails/leads/missing-items.blade.php
    - resources/views/emails/leads/rejection.blade.php
    - app/Livewire/Leads/Modals/MissingItemsModal.php
    - resources/views/livewire/leads/modals/missing-items-modal.blade.php
    - app/Livewire/Leads/Modals/RejectLeadModal.php
    - resources/views/livewire/leads/modals/reject-lead-modal.blade.php
    - tests/Unit/LeadCommunicationServiceTest.php
    - tests/Feature/LeadCommunicationModalTest.php
    - tests/Feature/AbusiveApplicantBlockingTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Forms/Services/DuplicateSubmissionGuard.php
    - app/Livewire/Leads/LeadProfile.php
    - resources/views/livewire/leads/lead-profile.blade.php

key-decisions:
  - "Bound rejection reasons strictly to the 13 standardized taxonomy codes in VERIFICATION-DICTIONARY.md Section 7, preventing vague free-text rejections"
  - "Enforced managerial authority (leads.reject) for rejecting leads and blocking applicant emails per MODULE-REGISTRY.md and T-01-07"
  - "Implemented assertEmailNotBlocked in DuplicateSubmissionGuard, checking leads table directly to satisfy public perimeter blocking without triggering withoutGlobalScopes architectural constraints"
  - "Preserved clean reapplication lifecycle for rejected applicants: previous rejection reasons are surfaced via amber alert banners on staff inspection per LEAD-07"

patterns-established:
  - "Missing items and rejection modals embedded on LeadProfile reacting to toolbar button dispatches"
  - "Audit events lead_missing_items_sent, lead_rejected, and lead_blocked appended to audit_logs"

requirements-completed:
  - LEAD-04
  - LEAD-07

coverage:
  - id: C1
    description: "Official missing items notifications dispatched with structured checklists and audit tracking"
    requirement: LEAD-04
    verification:
      - kind: unit
        ref: "tests/Unit/LeadCommunicationServiceTest.php"
        status: pass
      - kind: integration
        ref: "tests/Feature/LeadCommunicationModalTest.php"
        status: pass
    human_judgment: false
  - id: C2
    description: "Standardized rejection taxonomy enforcement and abusive applicant email blocking"
    requirement: LEAD-07
    verification:
      - kind: unit
        ref: "tests/Unit/LeadCommunicationServiceTest.php"
        status: pass
      - kind: integration
        ref: "tests/Feature/LeadCommunicationModalTest.php"
        status: pass
      - kind: integration
        ref: "tests/Feature/AbusiveApplicantBlockingTest.php"
        status: pass
    human_judgment: false

duration: 16min
completed: 2026-11-04
status: complete
---

# Phase 06 Plan 03: Lead Communication Workflows, Standardized Rejection System, and Abusive Email Blocking Guard Summary

**Official missing items communication service with structured document/photo checklists, 13-code standardized rejection taxonomy enforcement, interactive Livewire modal overlays on lead profile, and abusive email blocking guard at the public submission perimeter.**

## Performance

- **Duration:** ~16 min
- **Started:** 2026-11-04T10:55:22Z
- **Completed:** 2026-11-04T17:11:00Z
- **Tasks:** 3
- **Files modified:** 16

## Accomplishments

- Implemented official queued mailables (`MissingItemsNotificationMail` and `LeadRejectionMail`) with responsive, branded HTML/markdown templates per LEAD-04 and LEAD-07.
- Developed `LeadCommunicationService` enforcing the standardized 13-code rejection taxonomy from `VERIFICATION-DICTIONARY.md Section 7` (`INVALID_TXID`, `AMOUNT_MISMATCH`, `DUPLICATE_TXID`, `TRANSACTION_NOT_FOUND`, `DOCUMENT_UNREADABLE`, `EXPIRED_ID`, `NAME_MISMATCH`, `MISSING_GUARDIAN_CONSENT`, `NON_COMPLIANT_BACKGROUND`, `SELFIE_DETECTED`, `GLASSES_OBSCURING_FACE`, `EARS_COVERED`, `LOW_RESOLUTION`).
- Created interactive Livewire modal components (`MissingItemsModal` and `RejectLeadModal`) embedded on `LeadProfile`, featuring categorized missing item checklists, custom reviewer notes, live email previews, standardized rejection reason selection, and optional abusive email blocking toggles.
- Wired staff action toolbar buttons ("Request Missing Items" and "Reject Application") in `LeadProfile` guarded by Spatie permissions (`leads.communicate` and managerial `leads.reject`).
- Updated `DuplicateSubmissionGuard` with `assertEmailNotBlocked`, halting blocked applicants at the public form submission perimeter while preserving legitimate reapplication for applicants with previously rejected leads.
- Reapplication inspection: verified that reviewing staff see prior rejection dates and specific taxonomy codes in an amber alert banner when examining reapplicant profiles per LEAD-07.
- Verified test suite across 15 unit and feature tests (58 assertions) for Plan 06-03, and verified 38 baseline regression tests (232 assertions) with 0 regressions.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lead Communication Service and Official Lifecycle Mailables** - `86e8c01` (feat)
2. **Task 2: Interactive Missing Items and Standardized Rejection Modals on Lead Profile** - `dfd9e59` (feat)
3. **Task 3: Abusive Applicant Email Blocking Guard and Reapplication History Integration** - `11d8edb` (feat)

## Files Created/Modified

- `app/Domain/Leads/Mail/MissingItemsNotificationMail.php` - Queued mailable delivering structured checklist of missing documents/photos and instructions to applicants.
- `app/Domain/Leads/Mail/LeadRejectionMail.php` - Queued mailable delivering polite formal notice with standardized reason explanation and reapplication guidance.
- `resources/views/emails/leads/missing-items.blade.php` - Markdown email template with EMK Center branding, item checklist, and front desk contact details.
- `resources/views/emails/leads/rejection.blade.php` - Markdown email template with review findings and clean reapplication instructions.
- `app/Domain/Leads/Services/LeadCommunicationService.php` - Domain service managing missing item dispatches, standardized rejections, email blocking, and immutable audit logging.
- `app/Domain/Audit/Models/AuditLog.php` - Added event constants `lead_missing_items_sent`, `lead_rejected`, and `lead_blocked`.
- `app/Livewire/Leads/Modals/MissingItemsModal.php` - Livewire modal component handling categorized checklist selection, custom notes, and notification dispatch.
- `resources/views/livewire/leads/modals/missing-items-modal.blade.php` - Tailwind modal view with backdrop, categorized checkboxes, and live email preview.
- `app/Livewire/Leads/Modals/RejectLeadModal.php` - Livewire modal component enforcing taxonomy code selection and optional abusive email blocking.
- `resources/views/livewire/leads/modals/reject-lead-modal.blade.php` - Rose/danger themed modal view with taxonomy dropdown and applicant message preview.
- `app/Livewire/Leads/LeadProfile.php` - Added `refreshLead` event handler and modal opening dispatcher methods.
- `resources/views/livewire/leads/lead-profile.blade.php` - Added toolbar buttons and embedded modal components.
- `app/Domain/Forms/Services/DuplicateSubmissionGuard.php` - Added `assertEmailNotBlocked` and wired into `assertUniqueEmail`.
- `tests/Unit/LeadCommunicationServiceTest.php` - Unit tests verifying mail queuing, status transitions, taxonomy validation, and authorization barriers.
- `tests/Feature/LeadCommunicationModalTest.php` - Feature tests verifying modal rendering, form interaction, email blocking, and permission checks.
- `tests/Feature/AbusiveApplicantBlockingTest.php` - Feature tests verifying submission perimeter block, clean reapplication, history banner, and audit trail.

## Decisions Made

- Bound rejection reasons strictly to the 13 standardized taxonomy codes in `VERIFICATION-DICTIONARY.md Section 7`, preventing arbitrary or vague free-text rejection messages to applicants.
- Enforced managerial authority (`leads.reject`) for rejecting leads and blocking applicant emails per `MODULE-REGISTRY.md` and T-01-07.
- Implemented `assertEmailNotBlocked` in `DuplicateSubmissionGuard`, checking the `leads` table directly to satisfy public perimeter blocking without triggering `withoutGlobalScopes` architectural constraints.
- Preserved clean reapplication lifecycle for rejected applicants: previous rejection reasons are surfaced via amber alert banners on staff inspection per LEAD-07.

## Deviations from Plan

None - plan executed exactly as written.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 06-03 completed with official communication mailers, standardized rejection system, and abusive email blocking operational and tested.
- Ready for Plan 06-04: Atomic Lead-to-Member Conversion Engine and Invariant Verification Gate (DEC-03 10-step atomic transaction).
- No blockers.

## Self-Check: PASSED

- All 12 created files exist on disk: FOUND
- All 3 task commit hashes (`86e8c01`, `dfd9e59`, `11d8edb`) exist in git history: FOUND
- Test suites pass with 15 passed tests and 58 assertions for Plan 06-03: FOUND
- Regression test suites pass with 38 passed tests and 232 assertions: FOUND

---
*Phase: 06-leads-verification-and-controlled-conversion*
*Completed: 2026-11-04*
