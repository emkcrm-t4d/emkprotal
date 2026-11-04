---
phase: 06-leads-verification-and-controlled-conversion
plan: 02
subsystem: leads
tags: [leads, verification, tri-state, payment, bkash, adapter, documents, photo, consent, livewire]

requires:
  - phase: 06-leads-verification-and-controlled-conversion
    plan: 01
    provides: Lead directory, profile inspector, and lead verification schema baseline
  - phase: 02-architecture-data-model-and-security-design
    provides: Payment adapter contract architecture (ARCH-05, DEC-10) and Tri-State verification protocol (DEC-03)

provides:
  - Decoupled PaymentAdapterInterface and ManualBkashPaymentAdapter with duplicate TxID guard across leads and invoices
  - Tri-State verification domain engine (VerificationService) logging immutable evidence payloads to lead_verifications
  - Minor parental consent verification enforcement for applicants under 18 per DEC-09
  - Mandatory 5-point studio photo compliance evaluation for physical PVC badge printing
  - Interactive Livewire VerificationPanel partial embedded in LeadProfile with manager reconciliation UI and approval gate

affects:
  - 06-03-PLAN.md
  - 06-04-PLAN.md
  - 06-05-PLAN.md
  - 06-06-PLAN.md

actuals:
  tokens: 42000
  tasks: 3
  commits: 3
  plan_head_before: 9e3c570acf94cf92b85a0bc9c84be3dfabf1e250
  plan_head_after: e5f280acec04e803a5d9db8f5df324f4257713c9

tech-stack:
  added: []
  patterns:
    - "Decoupled payment driver pattern: PaymentAdapterInterface resolved via PaymentManager based on payments.default_driver"
    - "Cross-table duplicate TxID detection across active leads and settled invoices"
    - "Youth protection gating: age_at_submission < 18 mandates under_18_consent_verified before document verification can pass"
    - "5-point studio photo compliance checklist enforcing solid white background, no glasses, ears visible, regular attire, and no selfie"
    - "Atomic Manager approval gate enabled only when Payment, Document, and Photo streams are all verified"

key-files:
  created:
    - config/payments.php
    - app/Domain/Payments/DTOs/PaymentVerificationRequest.php
    - app/Domain/Payments/DTOs/PaymentVerificationResult.php
    - app/Domain/Payments/Contracts/PaymentAdapterInterface.php
    - app/Domain/Payments/Adapters/ManualBkashPaymentAdapter.php
    - app/Domain/Payments/Services/PaymentManager.php
    - app/Domain/Leads/Enums/VerificationStream.php
    - app/Domain/Leads/Enums/VerificationStatus.php
    - app/Domain/Leads/Exceptions/UnauthorizedVerificationException.php
    - app/Domain/Leads/Exceptions/UnmetConversionInvariantException.php
    - app/Domain/Leads/Services/VerificationService.php
    - app/Livewire/Leads/Partials/VerificationPanel.php
    - resources/views/livewire/leads/partials/verification-panel.blade.php
    - tests/Unit/ManualBkashPaymentAdapterTest.php
    - tests/Feature/VerificationServiceTest.php
    - tests/Feature/VerificationPanelTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - app/Domain/Leads/Models/LeadVerification.php
    - resources/views/livewire/leads/lead-profile.blade.php

key-decisions:
  - "Decoupled payment processing behind PaymentAdapterInterface and PaymentManager, allowing seamless Phase 12 gateway rollout with zero database schema alterations per ARCH-05 and DEC-10"
  - "Enforced 10-character uppercase alphanumeric regex (^[A-Z0-9]{10}$) and cross-entity uniqueness check on bKash TxID across leads and invoices with DUPLICATE_TXID rejection code per DEC-05"
  - "Enforced DEC-09 minor consent invariant in VerificationService: any applicant under 18 years old transitions to missing_items (MISSING_GUARDIAN_CONSENT) if parental consent is unverified"
  - "Structured 5-point studio compliance checklist (solid white background, no eyewear, ears visible, regular attire, formal portrait) required for photo verification prior to ID card printing"
  - "Gated Managerial approval strictly to users with leads.approve permission when Payment, Document, and Photo streams all evaluate to verified per DEC-03"

patterns-established:
  - "Tri-State Verification partial embedded into LeadProfile under the streams tab"
  - "Immutable evidence payloads stored as structured JSON in lead_verifications on every transition"

requirements-completed:
  - LEAD-02
  - LEAD-03

coverage:
  - id: V1
    description: "Decoupled manual bKash adapter validates 10-char uppercase TxID and catches duplicates across leads and invoices"
    requirement: LEAD-03
    verification:
      - kind: unit
        ref: "tests/Unit/ManualBkashPaymentAdapterTest.php"
        status: pass
    human_judgment: false
  - id: V2
    description: "VerificationService manages decoupled payment, document, photo streams and logs immutable evidence"
    requirement: LEAD-02
    verification:
      - kind: integration
        ref: "tests/Feature/VerificationServiceTest.php"
        status: pass
    human_judgment: false
  - id: V3
    description: "Livewire VerificationPanel renders tabbed tri-state interface with 5-point photo checklist and manager approval gate"
    requirement: LEAD-02
    verification:
      - kind: integration
        ref: "tests/Feature/VerificationPanelTest.php"
        status: pass
    human_judgment: false

duration: 20min
completed: 2026-11-04
status: complete
---

# Phase 06 Plan 02: Tri-State Verification Domain Engine and Interactive Verification Workspace Summary

**Decoupled bKash payment verification adapter with duplicate TxID prevention, independent Tri-State verification domain engine (Payment, Document, Photo), and interactive Livewire VerificationPanel with 5-point studio photo checklist and manager approval gate.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-11-04T10:34:27Z
- **Completed:** 2026-11-04T10:54:00Z
- **Tasks:** 3
- **Files modified:** 19

## Accomplishments

- Established the decoupled payment architecture (`PaymentAdapterInterface`, `PaymentManager`, `ManualBkashPaymentAdapter`, `PaymentVerificationRequest`, `PaymentVerificationResult`, and `config/payments.php`) per ARCH-05 and DEC-10.
- Implemented bKash Transaction ID validation with strict 10-character uppercase alphanumeric regex (`^[A-Z0-9]{10}$`) and dual-table collision detection across `leads` and `invoices` with `DUPLICATE_TXID` code per DEC-05 and LEAD-03.
- Built the `VerificationService` domain engine managing independent asynchronous review streams (Payment, Document, Photo) with permission boundaries (`leads.verify_payment`, `leads.verify_documents`, `leads.verify_photo`, `leads.approve`) and immutable structured evidence logging to `lead_verifications` per LEAD-02, DEC-03, and DEC-11.
- Enforced Youth Outreach Compliance (DEC-09): applicants under 18 years of age cannot achieve verified document status without verified guardian consent.
- Implemented the 5-point studio photo compliance checklist (white background, no glasses, ears visible, regular attire, formal portrait) required for plastic badge printing.
- Created the interactive Livewire `VerificationPanel` partial component and responsive Blade view embedded in `LeadProfile`, featuring duplicate TxID warning alerts, minor consent inspection, reactive photo checklist toggles, and manager approval gating.
- Verified test suite across 24 unit and feature tests (124 assertions) for Plan 06-02, and verified 14 baseline tracer tests (108 assertions) with 0 regressions.

## Task Commits

Each task was committed atomically:

1. **Task 1: Payment Adapter Foundation, Manual bKash Verification, and Duplicate TxID Guard** - `7f1b7e0` (feat)
2. **Task 2: Tri-State Verification Domain Engine and Immutable Evidence Logger** - `e237696` (feat)
3. **Task 3: Interactive Tri-State Verification Panel with 5-Point Studio Photo Checklist and Manager bKash Reconciliation UI** - `e5f280a` (feat)

## Files Created/Modified

- `config/payments.php` - Payment driver configuration defining default driver (`bkash_manual`) and driver settings.
- `app/Domain/Payments/Contracts/PaymentAdapterInterface.php` - Unified contract for payment providers.
- `app/Domain/Payments/DTOs/PaymentVerificationRequest.php` - Immutable DTO encapsulating verification inputs.
- `app/Domain/Payments/DTOs/PaymentVerificationResult.php` - Immutable DTO encapsulating standardized verification outcomes.
- `app/Domain/Payments/Adapters/ManualBkashPaymentAdapter.php` - Manual bKash payment driver with regex validation, collision detection, and evidence payload generation.
- `app/Domain/Payments/Services/PaymentManager.php` - Manager resolving active payment adapter driver.
- `app/Providers/AppServiceProvider.php` - Container binding for `PaymentManager` singleton and `PaymentAdapterInterface`.
- `app/Domain/Leads/Enums/VerificationStream.php` - Backed enum for review streams (`payment`, `document`, `photo`).
- `app/Domain/Leads/Enums/VerificationStatus.php` - Backed enum for verification statuses.
- `app/Domain/Leads/Exceptions/UnauthorizedVerificationException.php` - Authorization exception for staff without specific review authority.
- `app/Domain/Leads/Exceptions/UnmetConversionInvariantException.php` - Domain exception preventing approval/conversion when streams are incomplete.
- `app/Domain/Leads/Models/LeadVerification.php` - Added `reviewer()` relationship to verifier `User`.
- `app/Domain/Leads/Services/VerificationService.php` - Domain service handling stream transitions, minor consent checks, and approval gating.
- `app/Livewire/Leads/Partials/VerificationPanel.php` - Interactive Livewire partial component managing stream tabs, duplicate warnings, and submissions.
- `resources/views/livewire/leads/partials/verification-panel.blade.php` - Tailwind Blade view featuring 3-stream tabs, duplicate alerts, minor consent block, 5-point photo checklist, and approval action.
- `resources/views/livewire/leads/lead-profile.blade.php` - Embedded VerificationPanel partial into the "Verification Streams" tab.
- `tests/Unit/ManualBkashPaymentAdapterTest.php` - Unit tests for bKash adapter validation, auto-uppercasing, malformed input, and collisions.
- `tests/Feature/VerificationServiceTest.php` - Feature tests for `VerificationService` permissions, minor consent, 5-point photo criteria, and approval invariants.
- `tests/Feature/VerificationPanelTest.php` - Feature tests for `VerificationPanel` Livewire component mounting, tab switching, and stream submission actions.

## Decisions Made

- Decoupled payment processing behind `PaymentAdapterInterface` and `PaymentManager`, isolating core models from payment gateways and enabling smooth Phase 12 online checkout integration per ARCH-05 and DEC-10.
- Implemented database-wide uniqueness assertions for bKash TxIDs across both `leads.bkash_txid` and `invoices.payment_reference` (excluding the current lead record being reviewed), returning standardized `DUPLICATE_TXID` code and alerting reviewers per DEC-05.
- Embedded youth protection gating directly in `VerificationService::verifyDocuments`: minors (< 18) missing parental consent automatically receive `missing_items` status with reason `MISSING_GUARDIAN_CONSENT`.
- Bound photo verification approval to all 5 criteria in the studio checklist: solid white background, no eyewear, ears visible, regular attire, and direct frontal angle portrait.
- Conditioned the "Approve Lead for Induction" action strictly on `leads.approve` managerial authority and all 3 verification streams evaluating to `verified`.

## Deviations from Plan

None - plan executed exactly as written.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 06-02 completed with Tri-State verification engine and UI operational and tested.
- Ready for Plan 06-03: Applicant Communication & Missing Items Mailer (automated templates for missing docs, unreadable photos, and review updates).
- No blockers.

## Self-Check: PASSED

- All 16 created files exist on disk: FOUND
- All 3 task commit hashes (`7f1b7e0`, `e237696`, `e5f280a`) exist in git history: FOUND
- Test suites pass with 24 passed tests and 124 assertions for Plan 06-02: FOUND
- Regression test suites pass with 14 passed tests and 108 assertions: FOUND

---
*Phase: 06-leads-verification-and-controlled-conversion*
*Completed: 2026-11-04*
