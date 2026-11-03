---
phase: 01-discovery-decisions-and-acceptance-criteria
plan: 01-03
subsystem: governance
tags: [rbac, spatie, delegation, acceptance-criteria, gherkin, operational-targets, rpo, rto, zoho-migration, youth-protection, privacy]

requires:
  - 01-01 (MVP boundaries, decision register, workspace mapping)
  - 01-02 (field dictionary, status dictionary, verification dictionary)
provides:
  - Complete 5-role, 8-module RBAC delegation matrix with non-delegable security controls
  - 8 scenario-based acceptance criteria in Gherkin format covering all core workflows
  - Operational targets (500 members, 5 staff, RPO <= 1h, RTO <= 4h), backup runbook, cutover/rollback criteria, Zoho migration inventory, and privacy baseline
affects:
  - 02-architecture-data-model-and-security-design
  - 03-application-foundation-and-module-infrastructure
  - 04-identity-access-control-space-scope-and-audit-core
  - 09-member-portal-requests-and-read-only-access
  - 11-zoho-migration-pipeline-and-data-reconciliation
  - 12-release-verification-production-cutover-and-payment-gateway

actuals:
  tokens: 16833
  tasks: 3
  commits: 3
  plan_head_before: a1f017f864afbe22a4c8a881ee204c45a7942e21
  plan_head_after: fedb29919e96691a8b8eba8761430be5f1862f26

tech-stack:
  added: []
  patterns:
    - Multi-guard RBAC separation (web for staff/admin, member for member portal)
    - Static Spatie permission identifier syntax ({module}.{action})
    - Non-delegable managerial action allowlist preventing manager privilege escalation (T-01-07)
    - Scenario-based Gherkin acceptance criteria mapping Given-When-Then criteria to BDD/E2E test suites
    - Quantitative disaster recovery targets (RPO <= 1h, RTO <= 4h) with automated monthly restore verification drills
    - Safe zero-data-loss cutover with automated rollback triggers (failure rate > 2%, data mismatch > 0.1%)
    - Legacy Zoho record ID preservation (zoho_id) across all imported entities
    - Segregated minor data storage and 5-year post-expiry retention schedule (GAP-P1-04, DEC-09)

key-files:
  created:
    - .planning/phases/01-discovery-decisions-and-acceptance-criteria/DELEGATION-MATRIX.md
    - .planning/phases/01-discovery-decisions-and-acceptance-criteria/ACCEPTANCE-CRITERIA.md
    - .planning/phases/01-discovery-decisions-and-acceptance-criteria/OPERATIONAL-TARGETS.md
  modified: []

key-decisions:
  - "Hardcoded non-delegable actions (leads.reverse_conversion, leads.approve, leads.convert, platform_services.delegate_roles) in delegation service preventing staff privilege escalation (T-01-07)"
  - "Codified 8 formal Given-When-Then acceptance scenarios covering public form validation, tri-state verification, atomic conversion, 30-day reversal, workspace boundary defense, email change, read-only portal, and renewal"
  - "Locked operational capacity to 500 active members and 5 staff users with quantitative RPO <= 1h and RTO <= 4h recovery targets, rejecting any unquantified outage tolerance"
  - "Established cutover rollback triggers (failure rate > 2%, data discrepancy > 0.1%) and mandatory zoho_id preservation on all imported tables"
  - "Formalized data retention schedule (5 years post-expiration for members, 1 year for rejected leads, 10 years for immutable audit logs) with soft-delete restricted to Managers and hard-delete reserved for Superadmin"

patterns-established:
  - "Permission naming convention: {module}.{action} in lowercase snake_case without dynamic evaluation"
  - "Multi-guard separation: guard 'web' (staff) vs guard 'member' (portal) to structurally prevent permission bleed"
  - "Tenancy-aware permission evaluation: permissions evaluated strictly within authenticated workspace_id"
  - "Gherkin acceptance criteria: standard specification format binding feature tests and E2E browser tests"

requirements-completed:
  - DISC-04
  - DISC-05

coverage:
  - id: D-0103-1
    description: "Role, module, action, and workspace delegation matrix"
    requirement: "DISC-04"
    verification:
      - kind: other
        ref: "node -e \"const fs = require('fs'); const content = fs.readFileSync('.planning/phases/01-discovery-decisions-and-acceptance-criteria/DELEGATION-MATRIX.md', 'utf8'); if (!content.includes('workspace_manager') || !content.includes('delegated_staff') || !content.includes('leads.reverse_conversion') || !content.includes('DEC-01') || !content.includes('DEC-02')) process.exit(1);\""
        status: pass
    human_judgment: false
  - id: D-0103-2
    description: "Scenario-based acceptance criteria and E2E test matrix"
    requirement: "DISC-05"
    verification:
      - kind: other
        ref: "node -e \"const fs = require('fs'); const content = fs.readFileSync('.planning/phases/01-discovery-decisions-and-acceptance-criteria/ACCEPTANCE-CRITERIA.md', 'utf8'); if (!content.includes('Scenario 1') || !content.includes('Scenario 8') || !content.includes('Given') || !content.includes('When') || !content.includes('Then') || !content.includes('DEC-03')) process.exit(1);\""
        status: pass
    human_judgment: false
  - id: D-0103-3
    description: "Operational recovery targets, rollback criteria, migration inventory, and privacy baseline"
    requirement: "DISC-05"
    verification:
      - kind: other
        ref: "node -e \"const fs = require('fs'); const content = fs.readFileSync('.planning/phases/01-discovery-decisions-and-acceptance-criteria/OPERATIONAL-TARGETS.md', 'utf8'); if (!content.includes('RPO') || !content.includes('RTO') || !content.includes('500 active members') || !content.includes('Rollback') || !content.includes('zoho_id') || !content.includes('DEC-11')) process.exit(1);\""
        status: pass
    human_judgment: false

duration: 15min
completed: 2026-11-03
status: complete
---

# Phase 01 Plan 03: Delegation Matrix, Acceptance Criteria, and Operational Targets Summary

**Established definitive role and module delegation matrix with Spatie RBAC naming conventions, 8 formal Given-When-Then scenario acceptance criteria covering all critical workflows, and quantitative operational targets (500 members / 5 staff capacity, RPO <= 1h, RTO <= 4h), backup runbooks, cutover/rollback protocols, Zoho migration inventory, and privacy retention baselines.**

## Performance

- **Duration:** 15 min
- **Started:** 2026-11-03T13:30:00Z
- **Completed:** 2026-11-03T13:45:00Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

1. **Role, Module, Action, and Workspace Delegation Matrix (`DELEGATION-MATRIX.md`):** Codified the complete RBAC authorization architecture across 5 role archetypes (`superadmin`, `global_admin`, `workspace_manager`, `delegated_staff`, `member`) and 8 core modules (`forms`, `leads`, `members`, `member_portal`, `invoices`, `dashboard_reports`, `platform_services`, `migration`) encompassing 38 declared actions. Enforced strict workspace boundaries (**DEC-01 / D-01**), prohibited runtime dynamic code loading (**DEC-02 / D-02**), established standard Spatie permission strings (`{module}.{action}`), and hardcoded non-delegable actions (`leads.reverse_conversion`, `leads.approve`, `leads.convert`, `platform_services.delegate_roles`) in the delegation service to mitigate privilege escalation (**T-01-07**).
2. **Scenario-Based Acceptance Criteria and E2E Test Matrix (`ACCEPTANCE-CRITERIA.md`):** Formatted 8 exhaustive Given-When-Then acceptance scenarios covering the full tracer member lifecycle:
   - *Scenario 1:* Public 31-field form submission, minor consent rule (< 18), 10-char bKash TxID validation, and duplicate email prevention (**DEC-05, DEC-09**).
   - *Scenario 2:* Independent Tri-State verification (Payment, Document, Photo) with missing items email workflows and 5-point studio photo checklist (**DEC-03, DEC-05**).
   - *Scenario 3:* Atomic Lead-to-Member conversion transaction with pessimistic locking, dynamic term calculation, and sequential Member ID assignment (**DEC-03, DEC-08**).
   - *Scenario 4:* Controlled 30-day Conversion Reversal Protocol with mandatory >= 20-character rationale, voided invoices, and immutable audit logs (**DEC-04, DEC-11**).
   - *Scenario 5:* Multi-workspace tenancy boundary defense with Eloquent global scoping, route guards, and Livewire property tampering interception (**DEC-01**).
   - *Scenario 6:* Dedicated member email change request workflow alerting Workspace Manager and requiring formal approval (**DEC-07**).
   - *Scenario 7:* Read-only member portal access with forced first-login password reset and strict exclusion of internal staff notes and audit logs (**DEC-06, T-01-08**).
   - *Scenario 8:* Membership expiry and streamlined renewal flow opening 30 days prior to expiry without re-entering application fields (**DEC-08**).
3. **Operational Recovery Targets, Rollback Criteria, and Privacy Baseline (`OPERATIONAL-TARGETS.md`):** Established concrete operational targets rejecting ambiguous questionnaires:
   - *Capacity Envelope:* Baseline 500 active members, 5 concurrent staff users, and peak load of 50 submissions/hour.
   - *Availability & Disaster Recovery:* 99.5% uptime during operating hours (9:00 AM - 9:00 PM BST), **RPO <= 1 hour** (hourly transaction log snapshots), and **RTO <= 4 hours** (cold restore runbook with monthly automated drills).
   - *Cutover & Rollback:* Step-by-step 5-stage cutover sequence with quantifiable rollback triggers (submission failure rate > 2%, data mismatch > 0.1%, cross-workspace data leakage).
   - *Zoho Migration Inventory:* Entity mapping for leads, members, documents, and photos with mandatory preservation of original `zoho_id` and inactive portal staging.
   - *Privacy & Retention:* Segregated youth data storage, 5-year post-expiration retention for members, 1-year retention for rejected leads, 10-year immutable audit log retention, and strict deletion hierarchy (Staff = none, Manager = soft delete only, Superadmin = hard delete per **DEC-11**).

## Task Commits

Each task was committed atomically:

1. **Task 1: Role, Module, Action, and Workspace Delegation Matrix** - `1125d07` (feat)
2. **Task 2: Scenario-Based Acceptance Criteria and E2E Test Matrix** - `ec96600` (feat)
3. **Task 3: Operational Recovery Targets, Rollback Criteria, Migration Inventory, and Privacy Baseline** - `fedb299` (feat)

## Files Created/Modified

- `.planning/phases/01-discovery-decisions-and-acceptance-criteria/DELEGATION-MATRIX.md` - Complete 5-role, 8-module delegation matrix, Spatie identifier syntax, and non-delegable managerial action boundaries.
- `.planning/phases/01-discovery-decisions-and-acceptance-criteria/ACCEPTANCE-CRITERIA.md` - 8 end-to-end Given-When-Then scenario acceptance criteria and test execution matrix.
- `.planning/phases/01-discovery-decisions-and-acceptance-criteria/OPERATIONAL-TARGETS.md` - Measurable RPO/RTO targets, backup runbook, cutover/rollback criteria, Zoho migration inventory, and privacy retention rules.

## Decisions Made

- Enforced static Spatie permission string format `{module}.{action}` across 38 discrete actions, eliminating dynamic code execution risks.
- Hardcoded non-delegable actions in `DelegationPolicyService` ensuring Workspace Managers cannot grant staff access to reversal, conversion, or user delegation.
- Defined quantitative operational recovery metrics (RPO <= 1h, RTO <= 4h) for the 500-member pilot, replacing skipped questionnaire items with definitive engineering targets.
- Established strict cutover rollback triggers (failure rate > 2%, data discrepancy > 0.1%) to guarantee zero data loss during the transition from legacy Zoho.
- Locked retention schedule (5 years post-expiration for members, 1 year for rejected leads, 10 years for audit logs) with soft delete for Managers and hard delete exclusive to Superadmin.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All Phase 1 deliverables are now complete (`MVP-BOUNDARIES.md`, `DECISION-REGISTER.md`, `WORKSPACE-MAPPING.md`, `FIELD-DICTIONARY.md`, `VERIFICATION-DICTIONARY.md`, `STATUS-DICTIONARY.md`, `DELEGATION-MATRIX.md`, `ACCEPTANCE-CRITERIA.md`, `OPERATIONAL-TARGETS.md`).
- All 5 Phase 1 requirements (**DISC-01, DISC-02, DISC-03, DISC-04, DISC-05**) are fully satisfied.
- The project is fully unblocked and primed for Phase 2: Architecture, Data Model, and Security Design.

## Self-Check: PASSED

- All 3 deliverables created and verified (`DELEGATION-MATRIX.md`, `ACCEPTANCE-CRITERIA.md`, `OPERATIONAL-TARGETS.md`)
- All 3 task commits verified in git history (`1125d07`, `ec96600`, `fedb299`)
- Automated verification checks passed

---
*Phase: 01-discovery-decisions-and-acceptance-criteria*
*Completed: 2026-11-03*
