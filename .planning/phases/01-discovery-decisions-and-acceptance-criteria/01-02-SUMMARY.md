---
phase: 01-discovery-decisions-and-acceptance-criteria
plan: 01-02
subsystem: governance
tags: [validation, form-builder, tri-state-verification, lead-lifecycle, member-lifecycle, id-card, bkash]

requires:
  - 01-01 (MVP boundaries, decision register, workspace mapping)
provides:
  - Complete 31-field public membership application form validation contract
  - Tri-State verification protocol and Boolean ConversionAllowed gating invariant
  - Lead, Member, and ID card status dictionaries, state transition matrices, and reversal protocol
affects:
  - 02-architecture-data-model-and-security-design
  - 05-form-builder-and-public-membership-form
  - 06-leads-verification-and-controlled-conversion
  - 07-member-profiles-invoices-and-id-cards

actuals:
  tokens: 12811
  tasks: 3
  commits: 3
  plan_head_before: a4223e3e90effcf2016231d032d4403d16e3bb11
  plan_head_after: 1a38317e71f4758a275da8590091e1bef10b7a79

tech-stack:
  added: []
  patterns:
    - Independent concurrent Tri-State verification streams (Payment, Document, Photo)
    - Strict Boolean ConversionAllowed invariant gating Lead-to-Member conversion
    - 10-step atomic Lead-to-Member conversion transaction with pessimistic locking
    - Time-bounded (30 days), audited Conversion Reversal Protocol
    - Dynamic membership duration and expiry calculation from database terms (DEC-08)
    - 3-stage physical ID card tracking (provided_for_printing -> card_printed_ready_for_pickup -> delivered)

key-files:
  created:
    - .planning/phases/01-discovery-decisions-and-acceptance-criteria/FIELD-DICTIONARY.md
    - .planning/phases/01-discovery-decisions-and-acceptance-criteria/VERIFICATION-DICTIONARY.md
    - .planning/phases/01-discovery-decisions-and-acceptance-criteria/STATUS-DICTIONARY.md
  modified: []

key-decisions:
  - "31-field public membership form contract locked with dual-layer validation, dynamic under-18 parental consent, and 10-char uppercase bKash TxID"
  - "Independent Tri-State verification streams (Payment, Document, Photo) established with non-bypassable Boolean ConversionAllowed invariant"
  - "Lead-to-Member conversion formalized as a 10-step atomic transaction with 30-day auditable Conversion Reversal protocol"
  - "Member ID standardized as {SPACE_CODE}-{YEAR}-{5_DIGIT_SEQ} (e.g. EMK-2026-00001) with dynamic expiry dates driven by database terms"

patterns-established:
  - "Public form dual-layer validation: identical rules on client (Alpine.js) and server (Laravel FormRequest)"
  - "Tri-State verification: concurrent review of Payment, Document, and Photo with separate evidence capture schemas"
  - "Conversion invariant: ConversionAllowed = (Payment == 'verified') AND (Document == 'verified') AND (Photo == 'verified') AND (Lead == 'approved')"
  - "Member ID generation: Alphanumeric format {SPACE_CODE}-{YEAR}-{5_DIGIT_SEQ} using monotonic atomic sequence generator"

requirements-completed:
  - DISC-03

coverage:
  - id: D-0102-1
    description: "31-Field Public Membership Form Dictionary and Validation Contract"
    requirement: "DISC-03"
    verification:
      - kind: other
        ref: "node -e \"const fs = require('fs'); const content = fs.readFileSync('.planning/phases/01-discovery-decisions-and-acceptance-criteria/FIELD-DICTIONARY.md', 'utf8'); if (!content.includes('Field 31') || !content.includes('bKash Transaction ID') || !content.includes('Consent Form Upload') || !content.includes('DEC-09')) process.exit(1);\""
        status: pass
    human_judgment: false
  - id: D-0102-2
    description: "Tri-State Verification States, Evidence Logging, and Conversion Gating Invariant"
    requirement: "DISC-03"
    verification:
      - kind: other
        ref: "node -e \"const fs = require('fs'); const content = fs.readFileSync('.planning/phases/01-discovery-decisions-and-acceptance-criteria/VERIFICATION-DICTIONARY.md', 'utf8'); if (!content.includes('Tri-State') || !content.includes('Payment Verification') || !content.includes('Photo Verification') || !content.includes('ConversionAllowed') || !content.includes('DEC-03')) process.exit(1);\""
        status: pass
    human_judgment: false
  - id: D-0102-3
    description: "Lead, Member, and ID Card Status Dictionaries and State Transition Invariants"
    requirement: "DISC-03"
    verification:
      - kind: other
        ref: "node -e \"const fs = require('fs'); const content = fs.readFileSync('.planning/phases/01-discovery-decisions-and-acceptance-criteria/STATUS-DICTIONARY.md', 'utf8'); if (!content.includes('Lead Status') || !content.includes('Conversion Reversal') || !content.includes('provided_for_printing') || !content.includes('EMK-2026-') || !content.includes('DEC-04')) process.exit(1);\""
        status: pass
    human_judgment: false

duration: 15min
completed: 2026-11-03
status: complete
---

# Phase 01 Plan 02: Form Field, Status, and Verification Dictionaries Summary

**Established definitive data contracts for the 31-field public membership application form, independent Tri-State verification streams (Payment, Document, Photo) with non-bypassable conversion gating invariants, and Lead/Member/ID card lifecycle state machines with auditable reversal protocols.**

## Performance

- **Duration:** 15 min
- **Started:** 2026-11-03T13:23:00Z
- **Completed:** 2026-11-03T13:38:00Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

1. **31-Field Public Membership Form Dictionary (`FIELD-DICTIONARY.md`):** Codified the complete data contract for all 31 fields including exact database columns, input types, regex validation rules, allowed value enumerations, error messages, and reactive triggers. Mapped all 64 districts across Bangladesh's 8 administrative divisions. Resolved specification ambiguities by permitting hyphens and apostrophes in transliterated names, implemented conditional youth parental consent for applicants under 18 per **DEC-09 (D-09)**, and locked 10-character uppercase bKash Transaction ID validation per **DEC-05 (D-05)**.
2. **Tri-State Verification Protocol & Conversion Invariant (`VERIFICATION-DICTIONARY.md`):** Established three decoupled, concurrent verification streams (Payment, Document, Photo). Documented detailed review authority boundaries (Workspace Manager for payment; delegated staff for documents and photos), evidence capture JSON schemas, duplicate bKash TxID pre-check guards, and 5-point studio passport photo compliance checklists. Defined the strict Boolean conversion gating invariant formula:
   $$\text{ConversionAllowed} = (\text{Payment} = \text{'verified'}) \land (\text{Document} = \text{'verified'}) \land (\text{Photo} = \text{'verified'}) \land (\text{Lead} = \text{'approved'})$$
   Enforced at UI, service, and database levels per **DEC-03 (D-03)**. Published a standardized 13-code rejection taxonomy.
3. **Lifecycle State Machines & Conversion Reversal Protocol (`STATUS-DICTIONARY.md`):** Formalized the complete lifecycle state transitions for Leads (`draft` through `converted`, `rejected`, `blocked`), defined a 10-step atomic Lead-to-Member conversion transaction with pessimistic row-locking, and codified the 30-day Conversion Reversal Protocol with mandatory justification text and rollback actions per **DEC-04 (D-04)**. Standardized alphanumeric Member IDs (`EMK-2026-00001`), database-configurable membership terms and renewal calculations without hard-coded defaults per **DEC-08 (D-08)**, and established the 3-stage physical ID card issuance lifecycle (`provided_for_printing` $\rightarrow$ `card_printed_ready_for_pickup` $\rightarrow$ `delivered`).

## Task Commits

Each task was committed atomically:

1. **Task 1: 31-Field Public Membership Form Dictionary and Validation Contract** - `6d4362f` (feat)
2. **Task 2: Tri-State Verification States, Evidence Logging, and Verification Lifecycle** - `bde9b19` (feat)
3. **Task 3: Lead, Member, and ID Card Status Dictionaries and State Transition Invariants** - `1a38317` (feat)

## Files Created/Modified

- `.planning/phases/01-discovery-decisions-and-acceptance-criteria/FIELD-DICTIONARY.md` - Complete 31-field public membership form dictionary, regex rules, cascading geographic hierarchy, and conditional youth consent.
- `.planning/phases/01-discovery-decisions-and-acceptance-criteria/VERIFICATION-DICTIONARY.md` - Independent Tri-State verification protocol (Payment, Document, Photo), evidence capture schemas, duplicate TxID pre-checks, and ConversionAllowed invariant formula.
- `.planning/phases/01-discovery-decisions-and-acceptance-criteria/STATUS-DICTIONARY.md` - Lifecycle status dictionaries, state transition matrices, atomic conversion steps, 30-day reversal protocol, Member ID formatting, and 3-stage ID card lifecycle.

## Decisions Made

- Standardized Member ID scheme as `{SPACE_CODE}-{YEAR}-{5_DIGIT_SEQ}` (e.g., `EMK-2026-00001`) with sequential padding per workspace and cohort year.
- Validated bKash Transaction ID against both pending/past Leads and Member Invoices to block replay attacks.
- Formalized 5-point checklist for badge photos (white background, no glasses, ears visible, regular attire, no selfies) to eliminate printing defects.
- Restricted Conversion Reversal to Workspace Manager within 30 days of conversion, requiring a minimum 20-character rationale and 2FA confirmation.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- The field dictionary, verification schemas, and lifecycle state machines establish deterministic specifications for Plan 01-03 (Role/Module/Action Matrix, Migration Inventory, Privacy/Retention, Acceptance Scenarios) and Phase 2 (Architecture, Data Model, and Security Design).
- No blockers or unresolved critical items.

## Self-Check: PASSED

- All 3 deliverables created and verified (`FIELD-DICTIONARY.md`, `VERIFICATION-DICTIONARY.md`, `STATUS-DICTIONARY.md`)
- All 3 task commits verified in git history (`6d4362f`, `bde9b19`, `1a38317`)
- Automated verification checks passed

---
*Phase: 01-discovery-decisions-and-acceptance-criteria*
*Completed: 2026-11-03*
