# Roadmap: EMK Membership CRM & Member Portal

## Overview

This roadmap defines the 12-phase journey to replace legacy Zoho Forms and Zoho CRM with an integrated, modular membership CRM and read-only member portal inside the existing Hub. The delivery progression starts with governance, architectural schemas, and the Laravel 12 application foundation; proceeds through role-based access control, the dynamic form builder with the 31-field public membership form, lead verification, member profiles, official email integration, and the member portal; and concludes with workspace-scoped dashboards, Zoho data migration, and comprehensive security and operational release verification.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Discovery, Decisions, and Acceptance Criteria** - Baseline MVP boundaries, workspace mappings, dictionaries, delegation matrix, and acceptance criteria
- [x] **Phase 2: Architecture, Data Model, and Security Design** - Relational schema, module registry contracts, conversion invariants, reversal protocols, and payment adapter boundary
- [x] **Phase 3: Application Foundation and Deployment Baseline** - Laravel 12 / Livewire skeleton, Horizon queues, scheduler, private file storage, and CI/CD pipeline
- [ ] **Phase 4: Identity, Access Control, Space Scope, and Audit Core** - Role-based access control, strict server-side workspace isolation, staff delegation, and append-only audit trail
- [ ] **Phase 5: Form Builder and Public Membership Form** - Dynamic form builder and published 31-field public membership form with under-18 consent and bKash TxID rules
- [ ] **Phase 6: Leads, Verification, and Controlled Conversion** - Lead CRM, tri-state verification (Payment, Document, Photo), duplicate prevention, atomic conversion, and reversals
- [ ] **Phase 7: Member Profiles, Invoices, and ID Cards** - Member master profile, configurable terms/fees, post-conversion invoicing, and 3-stage ID card issuance lifecycle
- [ ] **Phase 8: Official Email, IMAP, Templates, and Workflow Rules** - Information Desk mailbox integration, threaded profile communications, Horizon email queue, and lifecycle triggers
- [ ] **Phase 9: Read-Only Member Portal, Change Requests, and Renewals** - Member authentication, read-only profile views, staff-reviewed change requests, email changes, and renewals
- [ ] **Phase 10: Dashboard, Reports, Data Tools, and Platform Settings** - Workspace-scoped operational dashboards, CSV/Excel export tools, admin controls, and encrypted backup routines
- [ ] **Phase 11: Zoho Migration and Reconciliation** - Legacy Zoho data/attachment migration, Zoho ID mapping, inactive portal account staging, reconciliation reports, and cutover
- [ ] **Phase 12: Verification, Security Review, Release, and Later Payment Gateway** - Security auditing, Livewire tampering defense, accessibility testing, production runbook, and payment gateway adapter interface

## Phase Details

### Phase 1: Discovery, Decisions, and Acceptance Criteria
**Goal**: Project stakeholders establish approved MVP boundaries, workspace-to-Space mappings, decision registers, and measurable acceptance criteria.
**Depends on**: Nothing (first phase)
**Requirements**: DISC-01, DISC-02, DISC-03, DISC-04, DISC-05
**Success Criteria** (what must be TRUE):
  1. Stakeholders have an approved decision and deferral register with owners and due dates for all Priority 1 governance items.
  2. Role/module/action matrix formalizes workspace isolation rules and staff delegation boundaries.
  3. Form field and status dictionary establishes exact validation rules for all 31 fields and lifecycle transitions.
  4. Measurable acceptance criteria and operational recovery targets are baselined for release sign-off.
**Plans**: 3/3 plans complete

Plans:
**Wave 1**
- [x] 01-01-PLAN.md — MVP Boundaries, Decision/Deferral Register, and Workspace-to-Space Mapping Baseline
- [x] 01-02-PLAN.md — Form Field, Status, and Tri-State Verification Dictionaries

**Wave 2**
- [x] 01-03-PLAN.md — Role/Module/Action Delegation Matrix, Scenario Acceptance Criteria, and Operational Recovery Targets

### Phase 2: Architecture, Data Model, and Security Design
**Goal**: Relational schemas, module registry boundaries, and security invariants are fully designed.
**Depends on**: Phase 1
**Requirements**: ARCH-01, ARCH-02, ARCH-03, ARCH-04, ARCH-05, ARCH-06
**Success Criteria** (what must be TRUE):
  1. Database schema models American Spaces, staff users, dynamic forms, leads, members, verification states, invoices, and audit logs.
  2. Conversion invariant contract guarantees zero lead-to-member conversion without completed payment, document, and photo verifications.
  3. Conversion reversal specification enforces mandatory reason capture and immutable audit logging.
  4. Replaceable payment adapter interface decouples payment provider implementations from core domain models.
**Plans**: 3/3 plans complete

Plans:
**Wave 1**
- [x] 02-01-PLAN.md — Central Module Registry Contract, Zero-Eval Architecture, and Relational Database Schema Baseline
- [x] 02-02-PLAN.md — Multi-Workspace Tenancy Defense-in-Depth and Atomic Conversion Lifecycle Invariants

**Wave 2**
- [x] 02-03-PLAN.md — Security Controls, Threat Modeling, and Replaceable Payment Adapter Boundary

### Phase 3: Application Foundation and Deployment Baseline
**Goal**: Deployable Laravel 12 application foundation, queue infrastructure, and automated deployment pipeline are operational.
**Depends on**: Phase 2
**Requirements**: FOUN-01, FOUN-02, FOUN-03, FOUN-04, FOUN-05
**Success Criteria** (what must be TRUE):
  1. Developers can build and deploy the Laravel 12 application skeleton with Livewire, Spatie Permission, and Alpine.js via automated CI.
  2. Laravel Horizon runs background queue workers with active health monitoring.
  3. Private file storage driver safely saves and retrieves uploaded documents using system-generated filenames.
  4. Baseline authenticated admin shell renders successfully and passes automated health checks.
**Plans:** 4/4 plans complete

Plans:
**Wave 1**
- [x] 03-01-PLAN.md — Laravel 12 Application Skeleton, Horizon Queues, Health Tracer, and CI Pipeline

**Wave 2**
- [x] 03-02-PLAN.md — Relational Database Schema Baseline, 13 Tables, Seeders, and MySQL Append-Only Audit Triggers

**Wave 3**
- [x] 03-03-PLAN.md — Central Module Registry, Zero-Eval Architecture Invariants, and Authenticated Admin Shell

**Wave 4**
- [x] 03-04-PLAN.md — Encrypted Private File Storage Driver, UUID Cryptographic Masking, and Authenticated Streaming Controller

### Phase 4: Identity, Access Control, Space Scope, and Audit Core
**Goal**: Multi-workspace role-based access control, scoped delegation, and append-only audit logging are enforced.
**Depends on**: Phase 3
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05
**Success Criteria** (what must be TRUE):
  1. Workspace Manager can log in and administer only their assigned workspace without discovering other workspaces.
  2. Workspace Manager can delegate authorized module actions to staff within their own workspace.
  3. Global Admin / Superadmin can navigate across workspaces.
  4. Every state transition, login event, and permission update records an immutable, read-only audit log entry excluding secrets.
**Plans:** 5 plans

Plans:
**Wave 1**
- [ ] 04-01-PLAN.md — Multi-Guard Authentication Baseline, Spatie RBAC Foundation, and End-to-End Tenancy Tracer

**Wave 2**
- [ ] 04-02-PLAN.md — Four-Layer Tenancy Defense Model & Automated Invariant Architecture Tests

**Wave 3**
- [ ] 04-03-PLAN.md — Scoped RBAC Delegation Policy, Staff Management, and Cache Invalidation
- [ ] 04-04-PLAN.md — Append-Only Immutable Audit Logging Core and Sensitive Secret Redaction

**Wave 4**
- [ ] 04-05-PLAN.md — Audit Log Viewer, Permission Cache Invalidation, and End-to-End Verification
**UI hint**: yes

### Phase 5: Form Builder and Public Membership Form
**Goal**: Public applicants can submit the official 31-field membership application with payments and file attachments.
**Depends on**: Phase 4
**Requirements**: FORM-01, FORM-02, FORM-03, FORM-04, FORM-05, FORM-06
**Success Criteria** (what must be TRUE):
  1. Public user can complete and submit the published 31-field membership application form online.
  2. Form dynamically displays and requires parent/guardian consent document upload for applicants under 18 years of age.
  3. Form validates a 10-character uppercase bKash Transaction ID and prevents duplicate email submissions.
  4. Form submission creates a Lead record with securely attached private files and dispatches an applicant confirmation email.
**Plans**: TBD
**UI hint**: yes

### Phase 6: Leads, Verification, and Controlled Conversion
**Goal**: Staff can review leads, execute independent tri-state verification checks, and atomically convert approved leads to members.
**Depends on**: Phase 5
**Requirements**: LEAD-01, LEAD-02, LEAD-03, LEAD-04, LEAD-05, LEAD-06, LEAD-07
**Success Criteria** (what must be TRUE):
  1. Staff can review workspace Leads and independently verify Payment, Documents, and Photo with evidence logging.
  2. System rejects duplicate bKash Transaction IDs and blocks conversion until all three verification checks are Complete.
  3. Workspace Manager can execute atomic Lead-to-Member conversion, safely re-associating documents with the new Member ID.
  4. Workspace Manager can reverse an erroneous conversion with a mandatory reason, reverting state and recording an immutable audit event.
  5. Reapplicants can reapply after rejection with historical rejection reasons visible to reviewing staff.
**Plans**: TBD
**UI hint**: yes

### Phase 7: Member Profiles, Invoices, and ID Cards
**Goal**: Staff can manage Member master profiles, configurable terms/fees, invoices, and ID card issuance lifecycles.
**Depends on**: Phase 6
**Requirements**: MEMB-01, MEMB-02, MEMB-03, MEMB-04, MEMB-05, MEMB-06
**Success Criteria** (what must be TRUE):
  1. Staff can view Member master profiles displaying identity, address, documents, payment history, and activity timeline.
  2. Membership start date is set to Manager approval date, and expiry is calculated dynamically from database-configured terms.
  3. Post-conversion invoices are generated automatically for new memberships and renewals.
  4. Staff can track ID cards through printing, ready-for-pickup notifications, and delivery.
  5. Member status restrictions and revocations require Admin authority and record management instructions.
**Plans**: TBD
**UI hint**: yes

### Phase 8: Official Email, IMAP, Templates, and Workflow Rules
**Goal**: Official mailbox is synchronized with CRM records, and automated lifecycle email workflows are operational.
**Depends on**: Phase 7
**Requirements**: MAIL-01, MAIL-02, MAIL-03, MAIL-04, MAIL-05
**Success Criteria** (what must be TRUE):
  1. Official Information Desk emails synchronize via IMAP and display as threaded conversations on Lead and Member profiles.
  2. Staff can manage and preview email templates for all application and membership milestones.
  3. System queues lifecycle email dispatches via Horizon with deduplication protection on retries.
  4. Automated workflow triggers dispatch notifications for status transitions and upcoming membership expirations.
**Plans**: TBD
**UI hint**: yes

### Phase 9: Read-Only Member Portal, Change Requests, and Renewals
**Goal**: Members can securely log into a read-only portal to view profile status and submit change or renewal requests.
**Depends on**: Phase 8
**Requirements**: PORT-01, PORT-02, PORT-03, PORT-04, PORT-05
**Success Criteria** (what must be TRUE):
  1. Member can authenticate using email or Member ID and is forced to set a new password on first login.
  2. Member can view approved profile details and documents while internal staff notes and audit logs remain hidden.
  3. Member can submit profile change requests for staff review rather than directly editing data.
  4. Member can initiate a dedicated Email Change request that alerts the Workspace Manager via email for approval.
  5. Member can submit a renewal request starting one month prior to expiry with manual payment details.
**Plans**: TBD
**UI hint**: yes

### Phase 10: Dashboard, Reports, Data Tools, and Platform Settings
**Goal**: Workspace managers and administrators can monitor operational metrics, export scoped data, and maintain system backups.
**Depends on**: Phase 9
**Requirements**: DASH-01, DASH-02, DASH-03, DASH-04, DASH-05
**Success Criteria** (what must be TRUE):
  1. Workspace Manager can view operational dashboards with counts, lead status distributions, and ID card tracking for their workspace.
  2. Staff can export filtered member data to CSV/Excel strictly within their authorized workspace scope.
  3. Administrator can manage staff roles, permissions, email templates, and platform parameters through protected settings screens.
  4. Automated and manual backups execute with encryption, protected access, and documented restore verification.
**Plans**: TBD
**UI hint**: yes

### Phase 11: Zoho Migration and Reconciliation
**Goal**: Legacy Zoho data and documents are imported, safely associated, and verified with zero reconciliation discrepancies.
**Depends on**: Phase 10
**Requirements**: MIGR-01, MIGR-02, MIGR-03, MIGR-04, MIGR-05
**Success Criteria** (what must be TRUE):
  1. Migration pipeline imports legacy Zoho Leads, Contacts/Members, and Form entries while preserving original Zoho record IDs.
  2. Legacy identity documents and photos are safely matched, renamed, and verified on imported profiles.
  3. Reconciliation report confirms 100% match on record counts, unique emails, and Active/Inactive statuses against Zoho sources.
  4. Cutover execution safely freezes Zoho Forms and redirects public applicants to the new membership form with tested rollback.
**Plans**: TBD

### Phase 12: Verification, Security Review, Release, and Later Payment Gateway
**Goal**: The complete system passes end-to-end security audits, production deployment, and payment adapter preparation.
**Depends on**: Phase 11
**Requirements**: RELS-01, RELS-02, RELS-03, RELS-04, RELS-05
**Success Criteria** (what must be TRUE):
  1. End-to-end security audit confirms workspace isolation and verifies defense against Livewire parameter tampering and route bypass.
  2. Accessibility, responsive layout, and cross-browser testing pass on public forms and member portal screens.
  3. Production deployment runbook completes with operational Horizon workers, scheduler verification, and admin provisioning.
  4. Payment adapter interface is validated and documented, ready for future SSLCommerz or direct bKash online gateway integration.
**Plans**: TBD
**UI hint**: yes

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Discovery, Decisions, and Acceptance Criteria | 3/3 | Complete | 2026-11-03 |
| 2. Architecture, Data Model, and Security Design | 3/3 | Complete    | 2026-11-03 |
| 3. Application Foundation and Deployment Baseline | 4/4 | Complete    | 2026-11-04 |
| 4. Identity, Access Control, Space Scope, and Audit Core | 0/0 | Not started | - |
| 5. Form Builder and Public Membership Form | 0/0 | Not started | - |
| 6. Leads, Verification, and Controlled Conversion | 0/0 | Not started | - |
| 7. Member Profiles, Invoices, and ID Cards | 0/0 | Not started | - |
| 8. Official Email, IMAP, Templates, and Workflow Rules | 0/0 | Not started | - |
| 9. Read-Only Member Portal, Change Requests, and Renewals | 0/0 | Not started | - |
| 10. Dashboard, Reports, Data Tools, and Platform Settings | 0/0 | Not started | - |
| 11. Zoho Migration and Reconciliation | 0/0 | Not started | - |
| 12. Verification, Security Review, Release, and Later Payment Gateway | 0/0 | Not started | - |
