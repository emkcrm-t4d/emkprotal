---
gsd_state_version: "1.0"
current_phase: 5
current_phase_name: Form Builder and Public Membership Form
current_plan: 2
status: in_progress
stopped_at: Completed 05-01-PLAN.md
last_updated: "2026-11-04T07:30:00.000Z"
last_activity: 2026-11-04
last_activity_desc: Phase 5 Plan 01 complete (Domain model, FormSchemaEngine, FormValidationCompiler, tracer slice)
state_head: 6ca7a45
progress:
  total_phases: 12
  completed_phases: 4
  total_plans: 20
  completed_plans: 16
  percent: 42
---

# Project State

## Project Reference

See: [.planning/PROJECT.md](file:///d:/EMK_Protal/.planning/PROJECT.md) (updated 2026-11-03)

**Core value:** Replace Zoho Forms and Zoho CRM with a modular membership CRM and read-only member portal inside the existing Hub, using separate workspaces for offices (launching with EMK Center Dhaka).
**Current focus:** Phase 5 — Form Builder and Public Membership Form

## Current Position

Phase: 5 — Form Builder and Public Membership Form
Current Plan: 05-02-PLAN.md (Wave 2)
Total Plans in Phase: 5
Next Phase: 6 (Leads, Verification, and Controlled Conversion)
Status: In progress
Last activity: 2026-11-04 — 05-01-PLAN.md executed and verified

Progress: [████░░░░░░] 42% (Phase 5 in progress)

## Performance Metrics

**Velocity:**
- Total plans completed: 15
- Average duration: 11.7 min
- Total execution time: 2.4 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| Phase 1 | 3 | 42 min | 14 min |
| Phase 2 | 3 | 21 min | 7 min |
| Phase 3 | 4 | 51 min | 12.8 min |
| 4 | 5 | - | - |

**Recent Trend:**
- Last 5 plans: 14m, 18m, 15m, 25m, 15m
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
| Phase 2 P03 | 7min | 2 tasks | 2 files |
| Phase 3 P01 | 15min | 3 tasks | 16 files |
| Phase 3 P02 | 10min | 3 tasks | 9 files |
| Phase 3 P03 | 12min | 3 tasks | 13 files |
| Phase 3 P04 | 14min | 3 tasks | 10 files |
| Phase 04 P01 | 18m | 3 tasks | 18 files |
| Phase 04 P02 | 15m | 3 tasks | 13 files |
| Phase 04 P03 | 25m | 3 tasks | 13 files |
| Phase 04 P04 | 15m | 3 tasks | 11 files |
| Phase 04 P05 | 15m | 3 tasks | 13 files |
| Phase 05 P01 | 12min | 3 tasks | 9 files |

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
- [Phase 2 P03]: Formalize OWASP ASVS Level 1 controls across 7 applicable categories including multi-guard separation, forced password reset on first member login per DEC-06 (D-06), and zero dynamic code evaluation
- [Phase 2 P03]: Enforce append-only audit log immutability at the MySQL engine tier via triggers rejecting UPDATE and DELETE with SQLSTATE 45000 per DEC-11 (D-11)
- [Phase 2 P03]: Store applicant identity documents, passports, and consent scans on a private storage disk outside the web root under cryptographic UUID filenames with streaming mediated by DocumentDownloadController per DEC-09 (D-09) and ARCH-06
- [Phase 2 P03]: Decouple core domain models from payment processors via PaymentAdapterInterface and PaymentManager driver pattern per ARCH-05 and DEC-10 (D-10)
- [Phase 2 P03]: Implement ManualBkashPaymentAdapter validating exact 10-character uppercase alphanumeric regex (^[A-Z0-9]{10}$), asserting global uniqueness across leads and invoices, and eliminating redundant second-person reviews per DEC-05 (D-05)
- [Phase 2 P03]: Provide seamless Phase 12 gateway rollout path (SSLCommerz, direct bKash) requiring only driver configuration changes with zero core schema modifications
- [Phase 2 Complete]: All 6 architecture requirements (ARCH-01 through ARCH-06) satisfied; Phase 2 complete and ready for Phase 3 (Application Foundation)
- [Phase 3 P01]: Initialize Laravel 12 directly in repository root with Livewire, Spatie Permission, and Horizon per FOUN-01
- [Phase 3 P01]: Configure multi-guard session isolation naming web staff cookie emk_web_session and member portal cookie emk_member_session per DEC-06
- [Phase 3 P01]: Configure MySQL 8.4 InnoDB engine with utf8mb4_unicode_ci collation and Redis Horizon supervisors per FOUN-02
- [Phase 3 P01]: Install containerized GitHub Actions CI pipeline running PHP 8.3, MySQL 8.4, and Redis with Pest testing per FOUN-05
- [Phase 3 P02]: Decouple physical American Spaces directory from logical operational workspaces per DEC-01 (D-01)
- [Phase 3 P02]: Preserve indexed unique zoho_id columns across users, leads, members, and invoices per DEC-02 (D-02)
- [Phase 3 P02]: Enforce database-level uniqueness on 10-character uppercase bKash TxID via uq_leads_bkash_txid per DEC-05 (D-05)
- [Phase 3 P02]: Implement dynamic term duration and fee structures in membership_terms without hardcoding per DEC-08 (D-08)
- [Phase 3 P02]: Install native MySQL 8.4 database triggers enforcing append-only immutability on audit_logs with SQLSTATE 45000 per DEC-11 (D-11)
- [Phase 3 P03]: Enforce zero dynamic code evaluation invariant across App namespace via Pest Arch tests per DEC-02 (D-02)
- [Phase 3 P03]: Hardcode 16 strictly non-delegable managerial actions in ModuleRegistry contract per T-01-07
- [Phase 3 P03]: Intercept disabled workspace modular routes with HTTP 404 in EnsureModuleEnabled middleware per MODULE-REGISTRY.md
- [Phase 3 P03]: Provide baseline responsive admin shell with workspace tenancy header (ws-emk-dhaka) and active module navigation per FOUN-04
- [Phase 3 P04]: Segregate applicant documents on private storage disk outside web root (storage/app/private) per FOUN-03 and DEC-09 (D-09)
- [Phase 3 P04]: Mask physical file names using UUID v4 strings, preserving client original filenames exclusively in metadata per ARCH-06
- [Phase 3 P04]: Calculate SHA-256 file hashes on upload and encrypt sensitive files at rest using AES-256
- [Phase 3 P04]: Require elevated leads.view_youth_records permission or workspace_manager role for minors flagged with youth_protection per DEC-09
- [Phase 3 P04]: Record document_accessed event in audit_logs table on every successful stream per DEC-11 (D-11)
- [Phase 3 Complete]: All 5 application foundation requirements (FOUN-01 through FOUN-05) satisfied; Phase 3 complete and ready for Phase 4 (Identity, Access Control, Space Scope, and Audit Core)
- [Phase 04]: Configured multi-guard authentication: web guard uses User and member guard uses MemberUser with global scope
- [Phase 04]: Migrated Spatie permissions tables and seeded 5 canonical roles and 38 declared actions from ModuleRegistry
- [Phase 04]: Enforced tenancy defense via VerifyWorkspaceAccess route middleware and TenantScope Eloquent global scope
- [Phase 04]: Enforced first-login password reset via EnsurePasswordReset middleware redirecting users with must_reset_password = true
- [Phase 04 P02]: Enforced Layer 1 Route Tenancy Middleware (VerifyWorkspaceAccess) validating slug syntax (^[a-z0-9-]+$) and rejecting cross-workspace route access with HTTP 403 per DEC-01
- [Phase 04 P02]: Implemented Layer 2 Livewire Hydration Guard (ValidatesWorkspaceOnHydration) intercepting entity tampering with HTTP 403 per TENANCY-SPEC.md
- [Phase 04 P02]: Bound Layer 3 Eloquent Global Scope (TenantScope) via BelongsToTenant across Lead and LeadAttachment models with fail-closed behavior for unassigned users
- [Phase 04 P02]: Enforced Pest Architecture tests strictly banning withoutGlobalScope across App\Http, App\Domain, and App\Livewire per DEC-01
- [Phase 04 P03]: Enforced strict 8-action delegable allowlist vs 16 strictly non-delegable managerial actions in DelegationPolicyService per T-01-07
- [Phase 04 P03]: Enforced TenancyBoundaryException on cross-workspace staff delegation, revocation, and deactivation attempts per DEC-01
- [Phase 04 P03]: Automated Spatie permission cache purging via PermissionRegistrar::forgetCachedPermissions() on every staff role and permission mutation per AUTH-05
- [Phase 04 P03]: Deployed Livewire StaffDirectory and RoleDelegation components under /crm/{workspace}/staff guarded by platform_services.delegate_roles and workspace tenancy
- [Phase 04 P04]: Configured auditable_type and auditable_id as nullable in audit_logs to support unauthenticated security events (failed logins with non-existent accounts)
- [Phase 04 P04]: Implemented AuditableObserver scrubbing 13 credential tokens to [REDACTED] in old_values and new_values per DEC-11 and ASVS V8.2
- [Phase 04 P04]: Bound AuditableObserver to User and Lead models and subscribed AuditAuthenticationEvents for login, failed login, logout, and password reset
- [Phase 05 P01]: Dropped composite unique uq_leads_email_workspace in favor of non-unique index idx_leads_email_workspace to support legitimate reapplicant submissions per FORM-05 and LEAD-07
- [Phase 05 P01]: Implemented zero-eval condition evaluator in FormSchemaEngine supporting all 13 field types and declarative comparison operators per DEC-02
- [Phase 05 P01]: Enforced 100-field DoS ceiling per form definition in FormSchemaEngine per T-05-02
- [Phase 05 P01]: Enforced exact 10-character uppercase alphanumeric bKash Transaction ID validation rule in FormValidationCompiler per DEC-05
- [Phase 05 P01]: Enforced conditional youth consent upload rule when applicant age derived from date_of_birth is under 18 per DEC-09

### Pending Todos

None - Phase 5 Plan 01 complete. Ready for Phase 5 Plan 02 (Form Builder Admin Interface & Seeded 31-Field Default Membership Form).

### Blockers/Concerns

None.

## Deferred Items

Items acknowledged and deferred at milestone close, most recent first:

| Category | Item | Status | Deferred At | Milestone |
|---|---|---|---|---|
| Migration Data Quality (GAP-P1-05) | Raw Zoho dump validation & verified contact count inventory | Deferred to Superadmin prior to Phase 11 | Phase 1 (Plan 01) | Phase 11 |
| Multi-Space Expansion (GAP-P1-01) | Administrative workspace provisioning wizard for remaining 5 Spaces | Deferred to Superadmin rollout planning | Phase 1 (Plan 01) | Phase 4 |

## Session Continuity

Last session: 2026-11-04T07:30:00Z
Stopped at: Completed 05-01-PLAN.md
Resume file: None
