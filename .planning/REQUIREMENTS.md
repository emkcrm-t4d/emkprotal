# Requirements: EMK Membership CRM & Member Portal

**Defined:** 2026-11-03
**Core Value:** Replace Zoho Forms and Zoho CRM with a modular membership CRM and read-only member portal inside the existing Hub, using separate workspaces for offices (launching with EMK Center Dhaka).

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Discovery & Acceptance

- [x] **DISC-01**: Publish approved MVP boundaries and decision/deferral register with owners and due dates for Priority 1 gaps.
- [x] **DISC-02**: Define workspace-to-Space mapping baseline launching with EMK Center Dhaka workspace.
- [x] **DISC-03**: Establish complete field and status dictionary covering form fields, verification states, and lead/member statuses.
- [x] **DISC-04**: Define role/module/action/workspace matrix establishing Manager delegation boundaries and Superadmin cross-workspace authority.
- [x] **DISC-05**: Document scenario-based acceptance criteria and recovery/rollback targets for release sign-off.

### Architecture & Security Design

- [x] **ARCH-01**: Define modular architecture registry with stable module keys and declared actions (avoiding runtime code loading).
- [x] **ARCH-02**: Design database schema for Spaces, staff users, forms, leads, members, verification states, invoices, ID cards, and audit logs.
- [x] **ARCH-03**: Define atomic conversion transaction invariant requiring complete Payment, Document, and Photo verification.
- [x] **ARCH-04**: Specify conversion reversal workflow with mandatory reason capture and immutable audit logging.
- [x] **ARCH-05**: Design replaceable payment adapter boundary supporting initial manual bKash verification and future payment gateways.
- [x] **ARCH-06**: Establish threat model, least-privilege security controls, and encrypted private document storage design.

### Application Foundation

- [x] **FOUN-01**: Set up Laravel 12 application skeleton with Livewire, Spatie Permission, Horizon, Tailwind CSS, and Alpine.js.
- [x] **FOUN-02**: Configure database, cache, queue workers, scheduled tasks, and logging infrastructure.
- [x] **FOUN-03**: Implement private file storage driver for applicant documents with safe system-generated filenames.
- [x] **FOUN-04**: Create central module registration foundation, initial migration baseline, and authenticated admin shell.
- [x] **FOUN-05**: Establish automated CI/deployment pipeline, health check endpoints, and environment runbook.

### Identity, Access & Audit

- [x] **AUTH-01**: Implement staff user management with global Admin/Superadmin, workspace Manager, and delegated staff roles.
- [x] **AUTH-02**: Enforce server-side workspace isolation on all routes, Livewire components, and queries for workspace staff.
- [x] **AUTH-03**: Enable workspace Managers to delegate specific module/actions to staff within their assigned workspace only.
- [x] **AUTH-04**: Implement append-only immutable audit log recording actor, timestamp, target entity, and old/new values (excluding secrets).
- [x] **AUTH-05**: Provide an audit log viewer for authorized staff and ensure permission cache invalidation upon access changes.

### Form Builder & Application Form

- [x] **FORM-01**: Build dynamic form builder supporting all required field types, instructions, rules, conditional formulas, and embed options.
- [x] **FORM-02**: Implement public 31-field Membership Application Form matching official specification and validation rules.
- [x] **FORM-03**: Enforce conditional parent/guardian consent form upload for applicants under 18 years of age.
- [x] **FORM-04**: Validate and enforce mandatory 10-character uppercase bKash Transaction ID on submission.
- [x] **FORM-05**: Enforce duplicate submission prevention and unique email validation across the platform.
- [x] **FORM-06**: Automatically create Lead record with securely stored private attachments and applicant confirmation email upon valid submission.

### Lead Management & Conversion

- [x] **LEAD-01**: Provide Lead management list and profile view with workspace scoping, filtering, and full activity timeline.
- [x] **LEAD-02**: Implement independent tri-state verification workflows for Payment, Documents, and Photo with notes and evidence logging.
- [x] **LEAD-03**: Provide manual bKash transaction verification with duplicate transaction ID detection and rejection reason recording.
- [x] **LEAD-04**: Support communication of missing or invalid application items via official email directly from the Lead profile.
- [x] **LEAD-05**: Execute atomic Lead-to-Member conversion only when Payment, Document, and Photo verifications are all Complete.
- [x] **LEAD-06**: Support controlled conversion reversal with required reason entry, permission verification, and immutable audit event.
- [x] **LEAD-07**: Preserve previous application rejection history on reapplication and allow Managers to block abusive emails.

### Member Profiles, Invoices & ID Cards

- [x] **MEMB-01**: Implement Member master profile displaying personal details, documents, payment history, notes, and activity timeline.
- [x] **MEMB-02**: Support database-configurable membership terms and fee structures with dynamic start and expiry date calculation.
- [x] **MEMB-03**: Manage Member statuses (Active, Inactive, Restricted, Revoked) with Admin-only restriction/revocation authority.
- [x] **MEMB-04**: Automatically generate post-conversion invoices and renewal payment records verified by workspace staff.
- [x] **MEMB-05**: Implement ID card issuance lifecycle tracking (Provided for Printing → Card Printed & Ready → Delivered) with pickup notification.
- [x] **MEMB-06**: Provide ID card replacement workflow recording replacement reason and restarting issuance cycle.

### Official Mailbox & Notifications

- [x] **MAIL-01**: Integrate Information Desk official mailbox via IMAP/SMTP with bi-directional message synchronization.
- [x] **MAIL-02**: Display threaded email history on Lead and Member profiles for all official correspondence.
- [x] **MAIL-03**: Provide email template manager for lifecycle notifications (receipt, missing items, rejection, approval, card ready, renewal).
- [x] **MAIL-04**: Queue email delivery via Laravel Horizon with automatic retries, deduplication safeguards, and failure monitoring.
- [x] **MAIL-05**: Support configurable automated workflow triggers based on status changes and membership expiry milestones.

### Member Portal & Requests

- [x] **PORT-01**: Provide secure Member authentication via email or Member ID with forced password reset on first login.
- [x] **PORT-02**: Display read-only profile information, uploaded documents, and communication history (hiding internal staff notes and audit logs).
- [x] **PORT-03**: Implement member profile change-request submission queue for staff review and approval (preventing direct member edits).
- [x] **PORT-04**: Provide dedicated Email Change request workflow notifying workspace Manager via email and requiring Manager approval.
- [x] **PORT-05**: Provide membership renewal request workflow opening 1 month prior to expiry with payment submission and staff verification.

### Dashboards, Reporting & Administration

- [ ] **DASH-01**: Build workspace-scoped operational dashboards for membership counts, lead pipeline, card issuance, and revenue metrics.
- [ ] **DASH-02**: Enforce cross-workspace dashboard view restrictions (Managers see own Space; only Admin/Superadmin view global data).
- [ ] **DASH-03**: Provide authorized CSV/Excel export tools scoped to workspace data with full audit event logging.
- [x] **DASH-04**: Provide administrative configuration screens for user management, role assignments, email templates, and system parameters.
- [x] **DASH-05**: Implement automated and manual database/file backup procedures with encryption and restore verification runbook.

### Zoho Migration & Cutover

- [ ] **MIGR-01**: Implement repeatable data import pipeline for Zoho Leads, Contacts/Members, form entries, documents, and photos.
- [ ] **MIGR-02**: Map legacy fields, terms, and statuses while preserving Zoho record IDs for audit traceability.
- [ ] **MIGR-03**: Safely associate legacy documents and photos with imported member profiles under secure naming conventions.
- [ ] **MIGR-04**: Generate reconciliation reports verifying record counts, active/inactive statuses, unique emails, and dates against Zoho source data.
- [ ] **MIGR-05**: Execute dry-run verification, import portal accounts in inactive state, and establish cutover/rollback procedures.

### Release Verification & Payment Gateway

- [ ] **RELS-01**: Conduct end-to-end UAT verification of multi-workspace isolation, conversion invariants, and permission boundaries.
- [ ] **RELS-02**: Perform security review addressing Livewire authorization tampering, route security, file upload security, and audit immutability.
- [ ] **RELS-03**: Execute accessibility, responsive layout, and cross-browser validation across public forms and member portal.
- [ ] **RELS-04**: Implement production deployment runbook, database migration verification, Horizon worker checks, and named admin provisioning.
- [ ] **RELS-05**: Implement payment gateway adapter interface preparing for seamless future SSLCommerz or bKash direct online payments.

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Automated Payment Gateway

- **GATE-01**: SSLCommerz automated checkout redirect and IPN webhook callback processing.
- **GATE-02**: bKash direct merchant payment gateway integration for automated instant payment verification.

### Multi-Office Onboarding

- **OFFC-01**: Superadmin workspace provisioning wizard for American Center, Barishal, Chattogram, Rajshahi, and Sylhet Corners.
- **OFFC-02**: Inter-workspace reporting rollup for country-wide diplomatic and cultural mission analytics.

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Direct member self-editing of profile | Violates data integrity; updates require staff-reviewed change requests |
| In-portal self-service cancellation | Membership restriction/revocation requires Admin authority and EMK management instruction |
| Member portal attendance tracking | Attendance is out of scope for membership CRM v1 release |
| Hard-coded two-year membership terms | Terms and fees must be configurable database records |
| Runtime arbitrary code/plugin execution | Security boundary violation; modules must declare actions statically |
| Database storage of payment gateway secrets | Deployment-managed secret environment variables required |
| Cross-workspace discovery for workspace staff | Strict isolation invariant across physical office workspaces |
| Direct online payment checkout in initial release | Initial launch relies on manual bKash TxID verification; gateway adapter in v2 |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DISC-01 | Phase 1 | Complete |
| DISC-02 | Phase 1 | Complete |
| DISC-03 | Phase 1 | Complete |
| DISC-04 | Phase 1 | Complete |
| DISC-05 | Phase 1 | Complete |
| ARCH-01 | Phase 2 | Complete |
| ARCH-02 | Phase 2 | Complete |
| ARCH-03 | Phase 2 | Complete |
| ARCH-04 | Phase 2 | Complete |
| ARCH-05 | Phase 2 | Complete |
| ARCH-06 | Phase 2 | Complete |
| FOUN-01 | Phase 3 | Complete |
| FOUN-02 | Phase 3 | Complete |
| FOUN-03 | Phase 3 | Complete |
| FOUN-04 | Phase 3 | Complete |
| FOUN-05 | Phase 3 | Complete |
| AUTH-01 | Phase 4 | Complete |
| AUTH-02 | Phase 4 | Complete |
| AUTH-03 | Phase 4 | Complete |
| AUTH-04 | Phase 4 | Complete |
| AUTH-05 | Phase 4 | Complete |
| FORM-01 | Phase 5 | Complete |
| FORM-02 | Phase 5 | Complete |
| FORM-03 | Phase 5 | Complete |
| FORM-04 | Phase 5 | Complete |
| FORM-05 | Phase 5 | Complete |
| FORM-06 | Phase 5 | Complete |
| LEAD-01 | Phase 6 | Complete |
| LEAD-02 | Phase 6 | Complete |
| LEAD-03 | Phase 6 | Complete |
| LEAD-04 | Phase 6 | Complete |
| LEAD-05 | Phase 6 | Complete |
| LEAD-06 | Phase 6 | Complete |
| LEAD-07 | Phase 6 | Complete |
| MEMB-01 | Phase 7 | Complete |
| MEMB-02 | Phase 7 | Complete |
| MEMB-03 | Phase 7 | Complete |
| MEMB-04 | Phase 7 | Complete |
| MEMB-05 | Phase 7 | Complete |
| MEMB-06 | Phase 7 | Complete |
| MAIL-01 | Phase 8 | Complete |
| MAIL-02 | Phase 8 | Complete |
| MAIL-03 | Phase 8 | Complete |
| MAIL-04 | Phase 8 | Complete |
| MAIL-05 | Phase 8 | Complete |
| PORT-01 | Phase 9 | Complete |
| PORT-02 | Phase 9 | Complete |
| PORT-03 | Phase 9 | Complete |
| PORT-04 | Phase 9 | Complete |
| PORT-05 | Phase 9 | Complete |
| DASH-01 | Phase 10 | Pending |
| DASH-02 | Phase 10 | Pending |
| DASH-03 | Phase 10 | Complete |
| DASH-04 | Phase 10 | Complete |
| DASH-05 | Phase 10 | Complete |
| MIGR-01 | Phase 11 | Pending |
| MIGR-02 | Phase 11 | Pending |
| MIGR-03 | Phase 11 | Pending |
| MIGR-04 | Phase 11 | Pending |
| MIGR-05 | Phase 11 | Pending |
| RELS-01 | Phase 12 | Pending |
| RELS-02 | Phase 12 | Pending |
| RELS-03 | Phase 12 | Pending |
| RELS-04 | Phase 12 | Pending |
| RELS-05 | Phase 12 | Pending |

**Coverage:**
- v1 requirements: 65 total
- Mapped to phases: 65
- Unmapped: 0 ✓

---
*Requirements defined: 2026-11-03*
*Last updated: 2026-11-03 after initial definition*
