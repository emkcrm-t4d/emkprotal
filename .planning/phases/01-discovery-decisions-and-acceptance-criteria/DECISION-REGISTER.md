# Comprehensive Decision, Deferral, and Policy Register

**Phase:** 01-discovery-decisions-and-acceptance-criteria  
**Plan:** 01-01  
**Status:** Approved Baseline  
**Governing Documents:** [Docs/emk-membership-crm-plan-revision-10.md](file:///d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md) (Section 9 & Phase 1 Exit Gate), [.planning/PROJECT.md](file:///d:/EMK_Protal/.planning/PROJECT.md)

---

## 1. Overview & Governance Intent

In accordance with DISC-01 and the Phase 1 Exit Gate mandated by Section 9 of the source specification ([Docs/emk-membership-crm-plan-revision-10.md](file:///d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md)), this register resolves all architectural decisions (DEC-01 through DEC-11 / D-01 through D-11), Priority 1 discovery gaps, and Priority 2 specification items.

Unresolved items are formally recorded with designated owners, enforceable due dates, and unambiguous classifications (`Decided`, `Deferred`, or `Out-of-Scope`), guaranteeing that unaddressed operational targets or legacy migration scopes are never silently assumed.

---

## 2. Core Architectural Decisions (DEC-01 to DEC-11 / D-01 to D-11)

These eleven foundational decisions are fully locked and govern all downstream application and database phases.

| Decision ID | Title | Owner | Due Date | Status | Rationale | Downstream Implementation Reference |
|---|---|---|---|---|---|---|
| **DEC-01 (D-01)** | Multi-Workspace Isolation | Superadmin | 2026-11-03 | **Decided** | Protects office data privacy and boundaries by launching strictly with EMK Center Dhaka (`ws-emk-dhaka`). Workspace Managers and delegated staff cannot discover or view other workspaces. Global Admin and Superadmin retain cross-workspace management. | Phase 1 (Plan 01), Phase 2 (Schema), Phase 4 (Auth/Middleware) |
| **DEC-02 (D-02)** | Modular Central Registry | Lead Architect | 2026-11-03 | **Decided** | Application features (Forms, Leads, Members, Portal, Invoices, Mail, Reports) register declared actions in a central registry. Prohibits arbitrary runtime code execution or insecure plugin loading. | Phase 2 (Module Contracts), Phase 3 (Foundation Registry) |
| **DEC-03 (D-03)** | Tri-State Verification Gate | Center Manager | 2026-11-03 | **Decided** | Prevents incomplete, unverified, or fraudulent records from becoming members. Independent verification states for Payment, Documents, and Photo must all be `Complete` prior to conversion. Post-conversion invoices generated only upon approval. | Phase 1 (Plan 02), Phase 2 (State Machine), Phase 6 (Leads CRM) |
| **DEC-04 (D-04)** | Conversion Reversal Protocol | System Architect | 2026-11-03 | **Decided** | Enables administrative correction of erroneous conversions under strict controls. Reversals require Workspace Manager authority, a mandatory documented reason, and an immutable audit log entry. | Phase 1 (Plan 02), Phase 2 (Reversal Spec), Phase 6 (Lead Conversion) |
| **DEC-05 (D-05)** | Manual bKash 10-char TxID Verification | Workspace Manager | 2026-11-03 | **Decided** | Supports initial manual payment collection without third-party gateway friction. Validates exactly 10 uppercase alphanumeric characters, enforces system-wide uniqueness, and eliminates redundant second-person reviews. | Phase 1 (Plan 02), Phase 5 (Form Rules), Phase 6 (Payment Verification) |
| **DEC-06 (D-06)** | Read-Only Member Portal | Product Owner | 2026-11-03 | **Decided** | Protects member data integrity and verified identity records. Members have read-only access to their profile, documents, and communications; all updates require staff change requests. Forced password reset on first login. | Phase 1 (Plan 01), Phase 4 (Portal Auth), Phase 9 (Member Portal) |
| **DEC-07 (D-07)** | Dedicated Email Change Workflow | Workspace Manager | 2026-11-03 | **Decided** | Since member login and applicant identity rely on verified email, email change requests are routed specifically to the Workspace Manager panel with alert notifications, approval verification, and complete audit logging. | Phase 1 (Plan 02), Phase 6 (Lead Identity), Phase 9 (Portal Requests) |
| **DEC-08 (D-08)** | Database-Configurable Terms and Fees | Center Manager | 2026-11-03 | **Decided** | Eliminates rigid hard-coded two-year defaults or static pricing. Membership terms, durations, and fee tiers are dynamic database entities. Start date defaults to Manager approval date; renewal window opens 1 month prior to expiry. | Phase 1 (Plan 02), Phase 2 (Schema), Phase 7 (Member Master) |
| **DEC-09 (D-09)** | Under-18 Parental Consent Rule | Compliance Officer | 2026-11-03 | **Decided** | Protects minor applicant data and satisfies youth outreach compliance. The application form automatically calculates age from Date of Birth and enforces mandatory parental/guardian consent form upload for applicants under 18. | Phase 1 (Plan 02), Phase 5 (Form Builder), Phase 6 (Document Gate) |
| **DEC-10 (D-10)** | Replaceable Payment Provider Boundary | Tech Lead | 2026-11-03 | **Decided** | Isolates core CRM domain models (Leads, Members, Invoices) from payment infrastructure via a decoupled adapter interface. Allows seamless migration from manual bKash TxID verification to automated gateways (SSLCommerz/bKash API). | Phase 2 (Adapter Contracts), Phase 3 (Adapter Provider), Phase 12 (Gateway) |
| **DEC-11 (D-11)** | Append-Only Immutable Audit Log | Security Lead | 2026-11-03 | **Decided** | Ensures accountability, security forensics, and regulatory compliance. System logs capture actor, timestamp, target entity, and old/new values (excluding secrets). Tables are write/append-only; update/delete permissions are denied to all roles, including Admin. | Phase 2 (Audit Schema), Phase 4 (Audit Core & Viewer) |

---

## 3. Priority 1 Governance Gaps (Section 9 Specification Alignment)

Section 9 of the specification identifies six critical governance gaps that must be resolved or formally deferred with owners and target dates before baselining estimates or engineering implementation.

| Gap ID | Topic | Owner | Target Due Date | Status | Detailed Rationale & Resolution Strategy | Downstream Plan Reference |
|---|---|---|---|---|---|---|
| **GAP-P1-01** | Workspace Map & Hub Integration | Superadmin | 2026-11-15 | **Decided (Launch) / Deferred (Expansion)** | **Decided:** Launch workspace is strictly `ws-emk-dhaka` (EMK Center Dhaka) embedded within the existing Laravel Hub. Form displays all 6 American Spaces; EMK Center submissions route to `ws-emk-dhaka` while other selections route to a central Superadmin staging pool.<br>**Deferred:** Administrative tooling for provisioning dedicated workspaces for the other 5 American Spaces is deferred to Superadmin rollout planning in Phase 4. | Phase 1 (Plan 01: WORKSPACE-MAPPING.md), Phase 4 (Workspace Tooling) |
| **GAP-P1-02** | Application Form, Validation & Status Dictionaries | Lead Architect | 2026-11-05 | **Decided** | **Decided:** Full 31-field schema, regex validation rules, age calculations, tri-state verification definitions (Payment, Document, Photo), and complete Lead/Member lifecycle status transition graphs are fully specified and standardized in Phase 1 Plan 02 (`FIELD-DICTIONARY.md` and `STATUS-DICTIONARY.md`). | Phase 1 (Plan 02) |
| **GAP-P1-03** | Conversion Reversal Safeguards | System Architect | 2026-11-05 | **Decided** | **Decided:** Reversal is restricted to Workspace Manager role within 30 days of conversion. Requires mandatory justification text (min 20 characters), automatically cascades Member status to `Reversed`, preserves original Lead record and payment evidence, marks associated Invoice as `Voided`, and emits an immutable audit event. Detailed state transitions specified in Plan 02 and Phase 2. | Phase 1 (Plan 02), Phase 2 (ARCH-04) |
| **GAP-P1-04** | Privacy, Retention & Records Policy | Superadmin | 2026-11-20 | **Decided (Core Rules) / Baselined (Plan 03)** | **Decided:** Mandatory under-18 parental consent upload enforced. File storage encrypted on private disk. Workspace Managers possess soft-delete authority; Superadmin retains exclusive hard-delete authority and full audit log visibility. Data retention baseline established at 5 years post-membership expiry per Plan 03 (`PRIVACY-AND-RECORDS.md`). | Phase 1 (Plan 03: PRIVACY-AND-RECORDS.md) |
| **GAP-P1-05** | Migration Scope & Data Quality | Superadmin | 2026-12-01 | **Deferred** | **Deferred:** Superadmin will extract Zoho CRM and Zoho Forms raw data dumps, inspect legacy data quality, and compile a verified inventory of valid contacts, members, and attachments. Phase 1 Plan 03 establishes the technical migration mapping template (`MIGRATION-INVENTORY.md`), but final volume sign-off is deferred to Superadmin prior to Phase 11 execution. | Phase 1 (Plan 03: MIGRATION-INVENTORY.md), Phase 11 (Zoho Migration) |
| **GAP-P1-06** | Operational Recovery Targets & Acceptance Scenarios | Lead Architect / Superadmin | 2026-11-05 | **Decided** | **Decided:** Operational baseline locked at 500 members and 5 staff for launch. Recovery Point Objective (RPO) locked at 1 hour; Recovery Time Objective (RTO) locked at 4 hours. Automated daily encrypted snapshots. Scenario-based UAT criteria covering all 11 core architectural decisions codified in Phase 1 Plan 03 (`ACCEPTANCE-SCENARIOS.md`). | Phase 1 (Plan 03: ACCEPTANCE-SCENARIOS.md) |

---

## 4. Priority 2 Specification Decisions

The following secondary operational policies and technical parameters have been resolved for inclusion in corresponding phase designs:

| Item | Decision Policy | Owner | Status | Target Phase |
|---|---|---|---|---|
| **Form Character Constraints** | Standard punctuation (hyphens, apostrophes, periods, forward slashes, commas) permitted in names and address lines; strict regex prevents injection and malformed scripts. | Lead Architect | **Decided** | Phase 5 (Form Builder) |
| **Member Change Request Approvals** | Workspace Managers possess exclusive authority to approve or reject member-submitted change requests. Approved changes update member profiles and write to the audit log. | Center Manager | **Decided** | Phase 9 (Member Portal) |
| **Portal Invoice Visibility** | Invoices generated upon Lead conversion remain hidden from the read-only Member Portal by default in v1, viewable only by workspace staff. | Product Owner | **Decided** | Phase 7 (Invoices) / Phase 9 (Portal) |
| **Mailbox Synchronization** | Information Desk mailbox configured via IMAP/SMTP supporting both English and Bengali notification templates. Horizon queues manage dispatch retries with duplicate prevention. | Tech Lead | **Decided** | Phase 8 (Official Email) |
| **Backup Cadence & Encryption** | Daily automated encrypted database snapshots and private document storage sync; 30-day rolling backup retention with monthly restore drill verification. | DevOps / SysAdmin | **Decided** | Phase 3 (Foundation) / Phase 10 (Platform Settings) |
| **Member ID Scheme** | Format standardized as `EMK-YYYY-XXXXX` (e.g., `EMK-2026-00001`) with sequential padding per workspace. | Lead Architect | **Decided** | Phase 1 (Plan 02) / Phase 2 (Schema) |

---

## 5. Phase 1 Exit Gate Compliance

This register formally satisfies the governance requirements established in the Phase 1 Exit Gate (Section 9):
1. **No Assumed Requirements:** All six Priority 1 items have verified owners, specific due dates, and definitive status classifications.
2. **Explicit Deferrals:** Migration data quality validation (GAP-P1-05) and multi-Space workspace provisioning (GAP-P1-01 expansion) are explicitly classified as `Deferred` under Superadmin ownership rather than unverified engineering assumptions.
3. **Workspace Isolation Guard:** DEC-01, DEC-06, and GAP-P1-01 strictly affirm that workspace isolation is maintained server-side across all queries, routes, and exports, reserving cross-workspace visibility exclusively to Superadmin.
