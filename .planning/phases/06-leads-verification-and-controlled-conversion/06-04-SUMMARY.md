---
phase: 06-leads-verification-and-controlled-conversion
plan: 04
subsystem: leads
tags: [leads, conversion, members, invoices, id-cards, monotonic-sequence, transactions, audit]

requires:
  - phase: 06-leads-verification-and-controlled-conversion
    plan: 03
    provides: Tri-state verification engine, rejection taxonomy, and communication workflows
  - phase: 02-architecture-data-model-and-security-design
    provides: Atomic 10-step conversion specification (CONVERSION-SPEC.md)

provides:
  - Member domain entities (Member, Invoice, IdCard, MembershipTerm) with tenancy scopes and relationships
  - Deadlock-free monotonic Member ID sequence generator (MemberIdSequenceGenerator)
  - Mathematical conversion gating invariant exception (UnmetConversionInvariantException)
  - Atomic 10-step Lead-to-Member conversion transaction engine (LeadConversionService)
  - Member portal account provisioning with forced password reset and guard member per DEC-06
  - Post-conversion paid invoice generation (INV-{YEAR}-{SEQ}) per DEC-03
  - ID card printing queue record generation
  - Secure private document re-association from lead directory to member directory
  - Queued welcome email notification job with activation instructions
  - Append-only conversion_completed audit log entry per DEC-11

affects:
  - 06-05-PLAN.md
  - 06-06-PLAN.md
  - Phase 07 (Members & Cards)
  - Phase 08 (Member Portal)

actuals:
  tokens: 12120
  tasks: 2
  commits: 2
  plan_head_before: 295bbba8b36dfd51428a919e93ac8711edc0e203
  plan_head_after: 29cef245a28c0dbf8e8b4502ccad7399571db27c

tech-stack:
  added: []
  patterns:
    - "Strict lock hierarchy (member_id_sequences -> leads -> membership_terms) eliminating relational deadlocks under concurrent approvals"
    - "Mathematical invariant gating asserting verified payment, document, photo streams, approved status, minor consent, and active email uniqueness"
    - "Dynamic validity duration calculations from database membership_terms without hardcoded durations per DEC-08"
    - "Atomic all-or-nothing 10-step relational transaction with complete rollback on unexpected failures"

key-files:
  created:
    - app/Domain/Forms/Models/MembershipTerm.php
    - app/Domain/Members/Models/Member.php
    - app/Domain/Invoices/Models/Invoice.php
    - app/Domain/IdCards/Models/IdCard.php
    - app/Domain/Members/Services/MemberIdSequenceGenerator.php
    - app/Domain/Leads/Services/LeadConversionService.php
    - app/Jobs/SendMemberWelcomeEmailJob.php
    - app/Domain/Members/Mail/MemberWelcomeMail.php
    - resources/views/emails/members/welcome.blade.php
    - tests/Unit/MemberIdSequenceGeneratorTest.php
    - tests/Feature/LeadConversionServiceTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Leads/Exceptions/UnmetConversionInvariantException.php

key-decisions:
  - "Enforced strict pessimistic row-level locking order (member_id_sequences -> leads -> membership_terms) to eliminate deadlocks per CONVERSION-SPEC.md Section 3.2"
  - "Locked conversion authority strictly to Workspace Managers possessing leads.convert within the active workspace per MODULE-REGISTRY.md and T-01-07"
  - "Calculated member expiry date dynamically from membership_terms.duration_months added to manager approval date per DEC-08"
  - "Generated post-conversion paid invoice (INV-{YEAR}-{SEQ}) referencing verified bKash TxID per DEC-03"
  - "Provisioned member portal credentials with guard 'member', must_reset_password = true, and empty password requiring secure email activation per DEC-06"
  - "Migrated private uploaded identity/photo attachments to member directory upon conversion"

patterns-established:
  - "Monotonic Member ID allocation using composite {SPACE_CODE}-{YEAR}-{5_DIGIT_SEQ} format with zero-padded sequences and overflow guards"
  - "UnmetConversionInvariantException returning 422 HTTP status with structured map of unmet domain invariants"
  - "SendMemberWelcomeEmailJob queuing MemberWelcomeMail delivering credential and badge collection instructions"
  - "AuditLog recording conversion_completed event with auditable_type Member and full snapshot of member, lead, invoice, and term identifiers"

requirements-completed:
  - LEAD-05

coverage:
  - id: C1
    description: "Deadlock-free monotonic Member ID sequence generation partitioned by space code and calendar year"
    requirement: LEAD-05
    verification:
      - kind: unit
        ref: "tests/Unit/MemberIdSequenceGeneratorTest.php"
        status: pass
    human_judgment: false
  - id: C2
    description: "Atomic 10-step Lead-to-Member conversion transaction with mathematical invariant gating and transactional rollback"
    requirement: LEAD-05
    verification:
      - kind: integration
        ref: "tests/Feature/LeadConversionServiceTest.php"
        status: pass
    human_judgment: false

duration: 12min
completed: 2026-11-04
status: complete
---

# Phase 06 Plan 04: Atomic Lead-to-Member Conversion Transaction Engine and UI Invariant Gate Summary

**Atomic 10-step lead-to-member conversion engine with deadlock-free monotonic ID allocation ({SPACE}-{YEAR}-{SEQ}), multi-layer mathematical invariant gating, dynamic term date calculation per DEC-08, portal user provisioning per DEC-06, post-conversion invoice generation per DEC-03, ID badge print queueing, private document migration, and append-only audit logging per DEC-11.**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-11-04T17:15:25Z
- **Completed:** 2026-11-04T17:27:00Z
- **Tasks:** 2
- **Files modified:** 13

## Accomplishments

- Established core member domain models (`Member`, `Invoice`, `IdCard`, `MembershipTerm`) implementing `TenantEntityInterface` and `BelongsToTenant` with date/datetime casts and relational graph links.
- Implemented `MemberIdSequenceGenerator` with row-level pessimistic locking (`FOR UPDATE`) on `member_id_sequences`, guaranteeing strictly monotonic, collision-free `{SPACE_CODE}-{YEAR}-{5_DIGIT_SEQ}` Member IDs without deadlocks or sequence gaps.
- Implemented `UnmetConversionInvariantException` holding structured unmet condition reasons and returning HTTP status 422.
- Built `LeadConversionService` executing the full 10-step atomic relational transaction per `CONVERSION-SPEC.md`:
  1. **Lock hierarchy acquisition:** Locks `member_id_sequences` first, `leads` second, and `membership_terms` third to eliminate deadlock vulnerability under concurrent manager approvals.
  2. **Mathematical invariant gating:** Asserts payment, document, and photo verification streams are `verified`, overall lead status is `approved`, minor applicants (< 18) possess verified parental consent per DEC-09, and email does not conflict with an active/inactive/restricted member profile.
  3. **ID allocation:** Assigns composite formatted Member ID (`EMK-2026-00001`).
  4. **Dynamic date calculation & profile creation:** Calculates `start_date` (approval date) and dynamic `expiry_date` by adding `duration_months` from the active database term without hardcoding per DEC-08; creates Member copying all 31 verified applicant fields.
  5. **Portal account provisioning:** Provisions portal user in `users` (`guard = 'member'`, `must_reset_password = true`, `is_active = true`, `password = null`) per DEC-06.
  6. **Private document re-association:** Moves private uploaded documents on disk from `workspaces/{workspace_id}/leads/{lead_uuid}/` to `workspaces/{workspace_id}/members/{member_uuid}/`.
  7. **Post-conversion invoice creation:** Instantiates paid invoice (`INV-{YEAR}-{SEQ}`) referencing the applicant's verified bKash TxID and term fee per DEC-03.
  8. **ID card queueing:** Creates plastic ID card record in `provided_for_printing` status for batch printing workflows.
  9. **Lead status transition:** Updates lead status to `converted` and records foreign key link to `converted_member_id`.
  10. **Welcome notification dispatch & audit trail:** Queues `SendMemberWelcomeEmailJob` delivering `MemberWelcomeMail` with portal activation and badge pickup instructions, and writes immutable `conversion_completed` event to `audit_logs` per DEC-11.
- Implemented `SendMemberWelcomeEmailJob`, `MemberWelcomeMail`, and responsive markdown template `resources/views/emails/members/welcome.blade.php`.
- Verified test coverage with 16 passed tests (66 assertions) across unit and feature suites for Plan 06-04, and verified 53 regression tests (290 assertions) with 0 failures.

## Task Commits

Each task was committed atomically:

1. **Task 1: Member Domain Entities, Monotonic Sequence Generator, and Invariant Exceptions** - `a05817e` (feat)
2. **Task 2: Atomic 10-Step Lead-to-Member Conversion Transaction Engine** - `29cef24` (feat)

## Files Created/Modified

- `app/Domain/Forms/Models/MembershipTerm.php` - Eloquent model for workspace membership terms with decimal fee cast and tenancy scope.
- `app/Domain/Members/Models/Member.php` - Member master domain entity with date casts, tenancy scope, and relationships to lead, user, term, invoices, and id cards.
- `app/Domain/Invoices/Models/Invoice.php` - Post-conversion billing invoice model with decimal amount cast and tenancy scope.
- `app/Domain/IdCards/Models/IdCard.php` - Physical membership ID card model with badge printing status and delivery timestamps.
- `app/Domain/Members/Services/MemberIdSequenceGenerator.php` - Pessimistic row-locking sequence generator producing collision-free Member IDs.
- `app/Domain/Leads/Exceptions/UnmetConversionInvariantException.php` - Domain exception capturing unsatisfied verification streams and 422 status code.
- `app/Domain/Leads/Services/LeadConversionService.php` - Atomic 10-step conversion engine coordinating lock acquisition, validation, record creation, file migration, invoicing, card queueing, and auditing.
- `app/Jobs/SendMemberWelcomeEmailJob.php` - Queued Horizon job dispatching welcome email to new members.
- `app/Domain/Members/Mail/MemberWelcomeMail.php` - Mailable delivering member credentials, validity period, and portal activation button.
- `resources/views/emails/members/welcome.blade.php` - Branded markdown email template for newly converted members.
- `app/Domain/Audit/Models/AuditLog.php` - Added `EVENT_CONVERSION_COMPLETED` and `EVENT_CONVERSION_REVERSED` constants.
- `tests/Unit/MemberIdSequenceGeneratorTest.php` - Unit test verifying monotonicity, space code partitioning, calendar year partitioning, and overflow handling.
- `tests/Feature/LeadConversionServiceTest.php` - Feature test verifying successful 10-step atomic execution, invariant blocking, transactional rollback, and authorization checks.

## Decisions Made

- Enforced strict pessimistic row-level locking order (`member_id_sequences` -> `leads` -> `membership_terms`) to eliminate deadlocks per `CONVERSION-SPEC.md Section 3.2`.
- Locked conversion authority strictly to Workspace Managers possessing `leads.convert` within the active workspace per `MODULE-REGISTRY.md` and T-01-07.
- Calculated member expiry date dynamically from `membership_terms.duration_months` added to manager approval date per DEC-08.
- Generated post-conversion paid invoice (`INV-{YEAR}-{SEQ}`) referencing verified bKash TxID per DEC-03.
- Provisioned member portal credentials with guard `member`, `must_reset_password = true`, and empty password requiring secure email activation per DEC-06.
- Migrated private uploaded identity/photo attachments to member directory upon conversion.

## Deviations from Plan

None - plan executed exactly as written.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 06-04 completed with core conversion transaction engine fully operational, tested, and guarded by mathematical invariants.
- Ready for Plan 06-05: Livewire Conversion UI, Manager Approval Modals, and Gating Tooltips on Lead Profile.
- No blockers.

## Self-Check: PASSED

- All 11 created files exist on disk: FOUND
- All 2 task commit hashes (`a05817e`, `29cef24`) exist in git history: FOUND
- Test suites pass with 16 passed tests and 66 assertions for Plan 06-04: FOUND
- Regression test suites pass with 53 passed tests and 290 assertions: FOUND

---
*Phase: 06-leads-verification-and-controlled-conversion*
*Completed: 2026-11-04*
