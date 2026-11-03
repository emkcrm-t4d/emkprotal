---
gsd_state_version: "1.0"
current_phase: 2
current_phase_name: Architecture, Data Model, and Security Design
status: executing
stopped_at: Completed 02-02-PLAN.md
last_updated: "2026-11-03T17:15:00.000Z"
last_activity: 2026-11-03
last_activity_desc: Completed 02-02-PLAN.md (Tenancy Defense & Atomic Conversion Invariant)
state_head: 5121fc1
progress:
  total_phases: 12
  completed_phases: 1
  total_plans: 6
  completed_plans: 5
  percent: 14
---

# Project State

## Project Reference

See: [.planning/PROJECT.md](file:///d:/EMK_Protal/.planning/PROJECT.md) (updated 2026-11-03)

**Core value:** Replace Zoho Forms and Zoho CRM with a modular membership CRM and read-only member portal inside the existing Hub, using separate workspaces for offices (launching with EMK Center Dhaka).
**Current focus:** Phase 2 — Architecture, Data Model, and Security Design

## Current Position

Phase: 2 (Architecture, Data Model, and Security Design) — EXECUTING
Next Plan: 02-03-PLAN.md (Security Controls, Threat Modeling, and Replaceable Payment Adapter Boundary)
Status: Executing Phase 2
Last activity: 2026-11-03 — Completed 02-02-PLAN.md (Tenancy Defense & Atomic Conversion Invariant)

Progress: [█░░░░░░░░░] 14% (Phase 2 in progress)

## Performance Metrics

**Velocity:**
- Total plans completed: 5
- Average duration: 11.2 min
- Total execution time: 0.93 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| Phase 1 | 3 | 42 min | 14 min |
| Phase 2 | 2 | 14 min | 7 min |

**Recent Trend:**
- Last 5 plans: 12m, 15m, 15m, 8m, 6m
- Trend: Consistent delivery velocity

*Updated after each plan completion*
**Per-Plan Metrics:**

| Plan | Duration | Tasks | Files |
|------|----------|-------|-------|
| Phase 1 P01 | 12min | 3 tasks | 3 files |
| Phase 1 P02 | 15min | 3 tasks | 3 files |
| Phase 1 P03 | 15min | 3 tasks | 3 files |
| Phase 2 P01 | 8min | 2 tasks | 2 files |
| Phase 2 P02 | 6min | 2 tasks | 2 files |

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table and [DECISION-REGISTER.md](file:///d:/EMK_Protal/.planning/phases/01-discovery-decisions-and-acceptance-criteria/DECISION-REGISTER.md).
Recent decisions affecting current work:

- [Project Setup]: Initialized 12-phase delivery roadmap covering Discovery, Architecture, Foundation, Identity, Form Builder, Leads, Members, Mail, Portal, Dashboards, Migration, and Release.
- [Architecture]: Modular architecture inside existing Hub, strictly isolating office workspaces, starting with EMK Center Dhaka.
- [Workflow]: Lead-to-Member conversion requires completed Tri-State verification (Payment, Document, Photo).
- [Phase 1]: DEC-01 through DEC-11 locked and bound to downstream architecture and foundation phases
- [Phase 1]: Launch workspace strictly limited to EMK Center Dhaka (ws-emk-dhaka); remaining American Spaces routed to Superadmin staging pool
- [Phase 1]: Strict server-side tenancy isolation enforced via Eloquent global scopes and 403 route guards
- [Phase 1 P02]: 31-field public membership form contract locked with dual-layer validation, dynamic under-18 parental consent, and 10-char uppercase bKash TxID
- [Phase 1 P02]: Independent Tri-State verification streams (Payment, Document, Photo) established with non-bypassable Boolean ConversionAllowed invariant
- [Phase 1 P02]: Lead-to-Member conversion formalized as a 10-step atomic transaction with 30-day auditable Conversion Reversal protocol
- [Phase 1 P02]: Member ID standardized as {SPACE_CODE}-{YEAR}-{5_DIGIT_SEQ} (e.g. EMK-2026-00001) with dynamic expiry dates driven by database terms
- [Phase 1 P03]: Complete 5-role, 8-module RBAC delegation matrix established with Spatie permission naming conventions ({module}.{action})
- [Phase 1 P03]: Non-delegable managerial actions (leads.reverse_conversion, leads.approve, leads.convert, platform_services.delegate_roles) hardcoded to prevent staff privilege elevation (T-01-07)
- [Phase 1 P03]: 8 formal Given-When-Then acceptance scenarios codified covering the complete tracer member lifecycle and tenancy defense
- [Phase 1 P03]: Quantitative operational targets locked (500 members / 5 staff capacity, RPO <= 1h, RTO <= 4h, cutover rollback failure rate > 2%)
- [Phase 1 P03]: Zoho migration inventory baseline established with mandatory zoho_id preservation and inactive portal staging
- [Phase 1 P03]: Privacy and data retention baseline locked (minor data segregation, 5-year post-expiry retention, Superadmin-exclusive hard delete per DEC-11)
- [Phase 1 Complete]: All 5 discovery requirements (DISC-01 through DISC-05) satisfied; Phase 1 complete and ready for Phase 2 (Architecture)
- [Phase 2 P01]: Enforce static ModuleRegistry singleton cataloging 8 core modules and 38 declared actions with zero dynamic code evaluation per DEC-02 (D-02)
- [Phase 2 P01]: Lock 16 managerial actions as strictly non-delegable to prevent staff privilege escalation per T-01-07
- [Phase 2 P01]: Decouple physical spaces directory from logical tenant workspaces, launching with EMK Center Dhaka (ws-emk-dhaka) per DEC-01 (D-01)
- [Phase 2 P01]: Enforce uq_leads_bkash_txid unique constraint on 10-char uppercase bKash TxID per DEC-05 (D-05)
- [Phase 2 P01]: Configure dynamic terms schema in membership_terms without hardcoded durations per DEC-08 (D-08)
- [Phase 2 P01]: Preserve zoho_id unique indexed columns across users, leads, members, and invoices for legacy migration reconciliation
- [Phase 2 P02]: Enforce multi-workspace tenancy across four server layers (Route Middleware, Livewire Hydration Guard, Eloquent TenantScope, and Private Storage Streaming) launching with ws-emk-dhaka per DEC-01 (D-01)
- [Phase 2 P02]: Strictly ban withoutGlobalScope() across all controllers, Livewire components, and reports via automated Pest Architecture tests
- [Phase 2 P02]: Require all three verification streams (Payment, Document, Photo) to be verified and lead approved before conversion is permitted per DEC-03 (D-03)
- [Phase 2 P02]: Acquire pessimistic row-level lock on member_id_sequences before leads to guarantee deadlock-free, monotonic Member ID allocation
- [Phase 2 P02]: Compute dynamic membership dates from membership_terms with start_date defaulting to Manager approval date per DEC-08 (D-08)
- [Phase 2 P02]: Permit conversion reversals strictly within 30 calendar days to Workspace Managers with minimum 20-character justification and cascading entity rollback per DEC-04 (D-04)

### Pending Todos

None - Plan 02-02 complete; Wave 2 Plan 02-03 next.

### Blockers/Concerns

None - Plan 02-03 unblocked.

## Deferred Items

Items acknowledged and deferred at milestone close, most recent first:

| Category | Item | Status | Deferred At | Milestone |
|---|---|---|---|---|
| Migration Data Quality (GAP-P1-05) | Raw Zoho dump validation & verified contact count inventory | Deferred to Superadmin prior to Phase 11 | Phase 1 (Plan 01) | Phase 11 |
| Multi-Space Expansion (GAP-P1-01) | Administrative workspace provisioning wizard for remaining 5 Spaces | Deferred to Superadmin rollout planning | Phase 1 (Plan 01) | Phase 4 |

## Session Continuity

Last session: 2026-11-03T17:15:00.000Z
Stopped at: Completed 02-02-PLAN.md
Resume file: None
