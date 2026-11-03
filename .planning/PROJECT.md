# EMK Membership CRM & Member Portal

## What This Is

A modular membership CRM and read-only member portal integrated directly into the existing Hub, replacing legacy Zoho Forms and Zoho CRM. It provides multi-workspace office isolation (launching with EMK Center Dhaka), managing the end-to-end lifecycle from public application to lead verification, member conversion, invoicing, ID card issuance, official email synchronization, and renewal workflows.

## Core Value

Replace Zoho Forms and Zoho CRM with a modular membership CRM and read-only member portal inside the existing Hub, using separate workspaces for offices (launching with EMK Center Dhaka).

## Business Context

- **Customer**: American Spaces in Bangladesh (launching with EMK Center Dhaka; future expansion to American Center, Barishal, Chattogram, Rajshahi, and Sylhet Corners), managed by Center Managers and delegated staff.
- **Revenue model**: Configurable membership fees per term (e.g., 1-year or multi-year terms) collected via manual bKash transaction verification (with future payment gateway adapter).
- **Success metric**: 100% replacement of Zoho Forms and Zoho CRM with zero migration data loss, zero cross-workspace data leakage, and 100% verified lead-to-member conversions.
- **Strategy notes**: Full specification defined in [Docs/emk-membership-crm-plan-revision-10.md](file:///d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md).

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] **Phase 1: Discovery, Decisions, and Acceptance Criteria** — Baseline MVP boundaries, workspace mappings, dictionaries, delegation matrix, and acceptance criteria.
- [ ] **Phase 2: Architecture, Data Model, and Security Design** — Relational schema, module registry contracts, conversion invariants, reversal protocols, and payment adapter boundary.
- [ ] **Phase 3: Application Foundation and Deployment Baseline** — Laravel 12 / Livewire skeleton, Horizon queues, scheduler, private file storage, and CI/CD pipeline.
- [ ] **Phase 4: Identity, Access Control, Space Scope, and Audit Core** — Role-based access control, strict server-side workspace isolation, staff delegation, and append-only audit trail.
- [ ] **Phase 5: Form Builder and Public Membership Form** — Dynamic form builder and published 31-field public membership form with under-18 consent and bKash TxID rules.
- [ ] **Phase 6: Leads, Verification, and Controlled Conversion** — Lead CRM, tri-state verification (Payment, Document, Photo), duplicate prevention, atomic conversion, and reversals.
- [ ] **Phase 7: Member Profiles, Invoices, and ID Cards** — Member master profile, configurable terms/fees, post-conversion invoicing, and 3-stage ID card issuance lifecycle.
- [ ] **Phase 8: Official Email, IMAP, Templates, and Workflow Rules** — Information Desk mailbox integration, threaded profile communications, Horizon email queue, and lifecycle triggers.
- [ ] **Phase 9: Read-Only Member Portal, Change Requests, and Renewals** — Member authentication, read-only profile views, staff-reviewed change requests, email changes, and renewals.
- [ ] **Phase 10: Dashboard, Reports, Data Tools, and Platform Settings** — Workspace-scoped operational dashboards, CSV/Excel export tools, admin controls, and encrypted backup routines.
- [ ] **Phase 11: Zoho Migration and Reconciliation** — Legacy Zoho data/attachment migration, Zoho ID mapping, inactive portal account staging, reconciliation reports, and cutover.
- [ ] **Phase 12: Verification, Security Review, Release, and Later Payment Gateway** — Security auditing, Livewire tampering defense, accessibility testing, production runbook, and payment gateway adapter interface.

### Out of Scope

- **Direct member self-editing of profile data** — Members have strictly read-only profile views; updates require staff-reviewed change requests to preserve data verification integrity.
- **Member portal self-cancellation** — No member cancellation action exists; membership restriction or revocation requires global Admin authority with management instructions.
- **Attendance tracking / logging in portal** — Out of scope for membership CRM v1 release.
- **Hard-coded two-year membership terms or fixed fees** — Terms and fees must be configurable database records.
- **Arbitrary runtime code or plugin execution** — Modules register declared actions in a central registry; runtime evaluation is prohibited for security.
- **Storing payment secrets or credentials in database/repository** — All secrets must reside in deployment-managed environment storage.
- **Direct cross-workspace data access for workspace staff** — Workspace Managers and delegated staff are strictly isolated to their assigned workspace.
- **Direct online payment checkout in initial release** — Initial launch relies on manual bKash transaction ID verification; direct gateway (SSLCommerz/bKash) will plug into the payment adapter in v2.

## Context

- The project replaces Zoho Forms and Zoho CRM for EMK Center Dhaka, removing third-party subscription friction and fragmented workflows.
- Integration takes place within the existing Hub web application.
- Operational capacity for launch is approximately 500 members and 5 staff.
- High data accuracy and privacy standards are mandated due to youth/student demographics (under-18 parental consent requirements) and sensitive verification documents (passports, national IDs, student certificates).
- Multi-office expansion will occur in subsequent iterations for other American Spaces in Bangladesh.

## Constraints

- **Tech Stack**: Laravel 12, Livewire, Spatie Laravel Permission, Laravel Horizon, Tailwind CSS, Alpine.js, PHP.
- **Workspace Isolation**: Enforced server-side at routes, Livewire actions, and database queries. Managers and delegated staff cannot discover or view other workspaces.
- **Tri-State Verification Invariant**: No Lead may be converted to Member until Payment, Document, and Photo verifications are all marked Complete.
- **Audit Immutability**: All sensitive events (logins, status changes, conversions, reversals, exports, permissions) are logged in an append-only store excluding secrets, read-only even for Admins.
- **Replaceable Payment Provider Boundary**: Decoupled via a payment adapter interface so that shifting from manual bKash verification to gateway APIs does not alter Lead or Member models.
- **Data Protection**: Uploaded applicant identity documents and photos are stored in private storage with safe system-generated filenames.

## Key Decisions

<decisions>
### Locked Architectural & Specification Decisions (ADR/Spec Revision 10)

- **DEC-01: Multi-Workspace Model**: Launch exclusively with EMK Center Dhaka workspace inside the existing Hub. Managers administer their assigned workspace only. Cross-workspace operations reserved for Admin/Superadmin.
- **DEC-02: Modular Application Architecture**: Modules (Forms, Leads, Members, Member Portal, Invoices, Dashboard/Reports, Platform Services, Migration) register declared actions in a central registry. No arbitrary runtime code loading.
- **DEC-03: Tri-State Verification Invariant**: No conversion of Lead to Member without completed verification of Payment, Documents, and Photo. Invoices generated only post-conversion.
- **DEC-04: Conversion Reversal**: Reversals permitted only with explicit staff authorization, recorded reason, and immutable audit event.
- **DEC-05: Manual bKash Verification**: Managers verify 10-character uppercase bKash transaction ID; duplicate IDs blocked. No second-person review required.
- **DEC-06: Read-Only Member Portal**: Members have read-only access to their profile and communication thread; all edits require audited staff change requests.
- **DEC-07: Dedicated Email Change Workflow**: Email updates require a dedicated request flow routed to the Workspace Manager with email notification.
- **DEC-08: Configurable Membership Terms**: Terms and fees are database records (never hard-coded 2-year defaults); membership start date defaults to Manager approval date.
- **DEC-09: Under-18 Consent Rule**: Enforce mandatory parental/guardian consent upload for applicants under 18 years of age.
- **DEC-10: Replaceable Payment Provider Boundary**: Decouple payment verification via an adapter interface to support manual bKash initially and direct gateway later.
- **DEC-11: Append-Only Immutable Audit Log**: System logs record actor, timestamp, target, and diffs (excluding secrets), read-only even for Admin.
</decisions>

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Multi-Workspace Isolation | Ensures office privacy and independent delegation | ✓ Good |
| Tri-State Conversion Gate | Prevents incomplete/unpaid member creation | ✓ Good |
| Read-Only Member Portal | Protects member data integrity and prevents unverified edits | ✓ Good |
| Modular Central Registry | Prevents architectural drift without runtime code loading risks | ✓ Good |
| Replaceable Payment Adapter | Protects domain models from payment gateway shifts | ✓ Good |
| Append-Only Audit Logging | Guarantees compliance, accountability, and traceability | ✓ Good |

---
*Last updated: 2026-11-03 after initial project setup*
