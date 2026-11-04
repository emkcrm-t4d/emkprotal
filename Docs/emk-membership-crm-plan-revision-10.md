# EMK Membership CRM & Member Portal — Phase-by-phase implementation plan

## Goal and delivery principles

Replace Zoho Forms and Zoho CRM with a modular membership CRM and read-only member portal inside the existing Hub, using separate workspaces for offices. The launch workspace is EMK Center Dhaka. The Superadmin will decide and create workspaces for other Hubs later. Mapping the six American Spaces to workspaces remains open. The workflow is public application → Lead → staff verification → Member → renewal or expiry. Laravel 12, Livewire, Spatie Laravel Permission, Horizon, Tailwind CSS, and Alpine.js are the specified stack.

Treat modules as supported, versioned application features with declared actions. Superadmins can configure approved business settings and access rights through validated workflows. They cannot change secrets, bypass authorization, edit audit history, or load arbitrary code. Enforce authorization and Space scope on the server at routes, Livewire actions, and service/domain boundaries. Keep the payment provider replaceable so the initial manual bKash verification and later gateway do not reshape Lead or Member data.

## Phase 1 — Discovery, decisions, and acceptance criteria

Record the following answers as decided for planning:

- **Hub and access boundary:** The feature lives inside the existing Hub and provides separate workspaces. Each workspace has its own Manager. A Manager and delegated staff cannot see or access other workspaces. Admin and Superadmin can operate across workspaces. Launch with EMK Center Dhaka. The Superadmin will decide and create workspaces for other Hubs later. Mapping the six named American Spaces to workspaces remains undecided.
- **Business authority:** Workspace Managers make business decisions and review applications. Managers may delegate staff access by module and module action; staff can perform the granted actions. Managers administer their workspace. Admin and Superadmin have cross-workspace authority. The Superadmin will own the remaining migration and privacy decisions below; account provisioning and safeguards still need design.
- **Applications and decisions:** Duplicate submissions are not allowed. After rejection, an applicant may reapply; Managers must see the previous rejection and reason. Managers make final decisions. A conversion may be reversed, with a required reason and audit trail.
- **Email changes:** Members cannot directly change their email. They submit a request; it appears in the relevant workspace Manager panel and sends an email notification to that Manager. Staff process and audit the request.
- **Membership/payment:** Membership terms and fees are configurable database records. Membership starts on Manager approval, with the end date calculated from the selected term. Renewal opens one month before term end. Early renewal starts after the current term ends. Late renewal requires Manager confirmation and runs from that date through the selected term end date. Managers verify manual bKash payments using the transaction ID; duplicate IDs are rejected with a cause. No second-person review is required. Approval/conversion and renewal wait for payment confirmation.
- **Migration/privacy:** Zoho import/export remain permission-controlled module actions. The Superadmin will prepare the migration scope later. Under-18 applicants are covered by the stated age rule; Managers may soft-delete, and the Superadmin may view all logs and hard-delete. Consent wording, retention periods, file access controls, and deletion safeguards remain open.
- **Operational targets and acceptance:** Planning capacity is approximately 500 members and 5 staff. Recovery targets, hosting/support ownership, pilot users/workspace, measurable acceptance scenarios, sign-off, and rollback criteria remain open; the user skipped this question. “Any” outage tolerance is not a measurable target.

Still to decide before detailed design: exact form fields and validation; document/photo verification states and evidence; status dictionaries and transitions; Member ID format; restriction/revocation authority; member change-request approval policy; portal-visible fields/documents/invoices; password/recovery policy; mailbox and language rules; report/export requirements; hosting, backup, restore, monitoring, privacy, consent wording, and retention controls.

Deliverables: approved MVP boundaries, decision/defer register with owners and dates, workspace-to-Space map (EMK Center Dhaka launch; remaining mapping owned by Superadmin), field/status dictionary, role/module/action/workspace matrix, migration inventory, privacy/retention decisions, and scenario-based acceptance criteria.

## Phase 2 — Architecture, data model, and security design

- Define module boundaries and contracts for Forms, Leads, Members, Member Portal, Invoices, Dashboard/Reports, Platform Services, and Migration. Use a central registry for stable module keys and declared actions; avoid runtime code loading.
- Model American Spaces, staff users and scopes, dynamic forms/fields/rules, Leads, Members, documents/photos, verification states, invoices/payments, ID card lifecycle, portal accounts, update/renewal requests, email threads/templates, workflow rules, and audit events. Preserve Zoho record IDs for migration traceability.
- Specify stable permission names by module/action and Spatie role assignments. Model global Admin/Superadmin authority separately from workspace Manager and delegated staff grants. Enforce workspace scope for every Manager/staff query and action at routes, Livewire actions, and service/domain boundaries; cross-workspace access is reserved for Admin/Superadmin. Keep module enablement separate from permission grants, and audit delegation changes.
- Design the conversion transaction and invariant: no conversion until payment, document, and photo verification are Complete; no duplicate or incomplete application approval; invoice creation only after conversion. Define safe handling of uploaded files, queued work, and disabled modules.
- Define protected Admin controls, least privilege, session/password policy, recovery access, append-only audit storage, secret handling, PII/file access, and backup encryption/retention.
- Deliverables: architecture decisions, ER/data model, module/action catalog, authorization matrix, threat model, audit schema, migration strategy, and operational boundaries.

## Phase 3 — Application foundation and deployment baseline

- Establish Laravel 12, Livewire, Spatie Permission, Horizon, Tailwind CSS, and Alpine.js with environment configuration and initial CI/deployment pipeline.
- Set database, cache, queue, file storage, mail/IMAP integration approach, scheduler, logging, health checks, and private document storage. Keep credentials in deployment-managed secret storage.
- Create the module registration foundation, migrations, baseline admin shell, secure authentication/session setup, and protected Horizon access.
- Deliverables: deployable skeleton, environment setup, initial schema, CI baseline, and runbook.

## Phase 4 — Identity, access control, Space scope, and audit core

- Implement staff account lifecycle for global Admin/Superadmin, workspace Manager, delegated staff, and Member accounts. Configure permissions and role assignment through protected workflows; Managers administer their own workspace and grant staff only the module/actions permitted to them.
- Add module/action grants that Managers can delegate to staff within their own workspace, including view, create, edit, delete, export, import, verify payment, approve, convert, reverse conversion, and manage requests as applicable. Enforce workspace isolation server-side for all staff. Only Admin/Superadmin can cross workspace boundaries.
- Create protected baseline access and a recovery path; invalidate permission caches predictably after updates. Members have no direct data-edit permission.
- Implement append-only audit events for record changes, status transitions, conversions, file upload/replacement, email, login and failed login, password change/reset, exports/backups, access-right changes, and update/renewal request outcomes. Record actor, time, target, and old/new values while excluding secrets.
- Deliverables: identity/access workflows, policy conventions, system log viewer, profile timelines, and audit completeness rules.

## Phase 5 — Form builder and public Membership Form

- Build the Manager/Admin form builder so staff can change the form without a developer. Support the PDF's field types (text, dropdown/grouped dropdown, radio, email, number, checkbox, multiple choice, date/time, multi-file and photo uploads, legal/consent, page elements, instructions, and formulas); textbox input modes and casing; number formats/ranges; dropdown “Other” and sort order; configurable date/time formats and calculations; per-field instructions, visibility, required and no-duplicate settings; allowed file types/counts/sizes; and field, form, page, and submission rules with conditional formulas. Support open/close dates, thank-you/redirect, confirmation email, permalink, website embed/iframe/hyperlink/HTML/CSS, filterable entries, CSV/Excel, analytical reports, and submission history on the Lead timeline.
- Configure the Membership Form with the PDF's 31 fields and rules: (1) First Name, required, letters only, Aa; (2) Middle Name, optional, letters only, Aa; (3) Last Name, required, letters only, Aa; (4) American Spaces, required dropdown (American Center, EMK Center, Barishal Corner, Chattogram Corner, Rajshahi Corner, Sylhet Corner); (5) Sex, required (Male/Female/N/A); (6) Date of Birth, required MM/DD/YYYY and age calculation; (7) Consent Form Upload, conditionally shown and required below the confirmed threshold; (8–11) House No. (required), Road No. (optional), Area (required), ZIP (required), each any characters; (12) Division, required Bangladesh division dropdown; (13) District, required dropdown filtered by Division; (14) Contact Number, required digits in +880XXXXXXXXXX format and configured length; (15) Email, required valid format and unique across the system; (16) Website, optional; (17) Occupation, required (Artist, Community Worker, Educator, Entrepreneur, Media Professional, Performer, Student, Other with text); (18) Last/current place of study, required letters only, Aa; (19) Highest certification, required (SSC, O-level, HSC, A-level, Undergraduate Degree, Undergraduate Honors, Graduate, Postgraduate, Other with text); (20) Area of Subject, required letters only, Aa; (21) U.S. work/study, required Yes/No; (22) U.S. location, conditional on Yes, letters only, Aa; (23) American institution alumni, required Yes/No; (24) school, conditional on Yes, letters only, Aa; (25) Current Employer, optional, letters only, Aa; (26) Title, optional, letters only, Aa; (27) Work Phone, optional digits in +880XXXXXXXXXX format; (28) Documents Upload, required, pdf/jpg/jpeg/doc/png, maximum 2 files at 4 MB each; (29) Photo Upload, required, one jpg/jpeg/png file up to 4 MB; (30) bKash Transaction ID, required, letters/numbers, uppercase, exactly 10 characters; and (31) required Terms and Conditions checkbox. Every submission maps these fields to Lead fields and creates one Lead with attached files.
- Include the PDF's applicant-facing header/instructions: membership form title; accepted identity-document alternatives; recent color studio passport photo guidance (white background, no glasses, ears visible, regular clothes, no selfies); non-refundable fee/payment instructions; downloadable signed parent/guardian consent form; and the instruction to enter the bKash Transaction ID. Treat the PDF's sample merchant number and 1,000 BDT/two-year statements as specification references only where they conflict with later decisions: term and fees remain database-configurable, payment is manually verified before approval/renewal, and no credentials/payment secrets belong in the plan.
- Keep the confirmed under-18 consent rule from the user's later answer. Record that this resolves the PDF's under-16/under-18 conflict for planning. Preserve other unresolved field/validation conflicts (including letters-only rules versus real-world names containing punctuation/digits) as decisions before form build; do not silently relax them. The six Spaces listed by the form remain distinct from the still-undecided workspace mapping.
- Support filtered entries, CSV/Excel export, analytical reports, and submission history on the Lead timeline, subject to the module/action permissions and workspace scope.
- On submit, create a Lead with attached files and the applicant-supplied bKash transaction ID. Match applicants by verified email. Reapplication is allowed after rejection; Managers can block an email from future applications. Show prior rejection causes/history to Managers. Do not impose other identity-match blockers. Send the applicant confirmation. Store files privately with safe generated names; display the intended applicant/member naming convention without trusting user filenames.
- Deliverables: configurable form builder, published form, Lead creation path, validation evidence, and form administration guide.

## Phase 6 — Leads, verification, and controlled conversion

- Build Lead list/profile, search/filtering, editable Admin/Manager fields, profile photo replacement, two editable status dictionaries with a validated mapping, and a full timeline.
- Implement separate Payment, Document, and Photo verification states with notes and evidence. Staff can identify missing items, update Current Status, and send an official email listing outstanding requirements.
- Managers make the final acceptance/rejection decision. Managers verify the manual bKash transaction and record evidence/outcome; approval/conversion and renewal are blocked until payment is confirmed and the required document/photo checks are complete. Convert Lead to Member atomically, copy/match fields and files, preserve prior rejection/reapplication history, and record the conversion audit event. Provide a controlled reversal/correction action requiring a reason, permission, and immutable audit event.
- Rename or associate documents safely with the new Member ID after conversion.
- Deliverables: Lead CRM, verification workflow, guarded conversion, status configuration, and acceptance evidence for allowed and denied conversions.

## Phase 7 — Member profiles, invoices, and ID cards

- Implement the Member master profile with applicant, address, document, photo, payment, membership, notes, email, invoices, portal access, and timeline sections. Only Admin/Manager may edit profiles.
- At approval/conversion, select a database-managed membership term and fee. Membership starts on the Manager approval date; calculate its end date from the configured term. Capture Member ID, joining date, expiry date, term, category, and paid amount. Renewal opens one month before expiry. Early renewal begins when the prior term ends. Late renewal requires Manager confirmation and starts on that date through the selected term end date. Do not hard-code a two-year default.
- Implement membership status (Active, Inactive, Restricted, Revoked), expiry scheduling, and restricted/revoked changes with Admin-only authority, reason, and required EMK management instruction. Do not provide a cancellation action.
- Create invoices only after conversion and for each renewal payment; Managers verify manual bKash payments. Invoice/payment record timing and the role/action model must be aligned to confirmed accounting requirements before implementation. Track ID card issuance from Provided for Printing → Card Printed & Ready to Collect → Delivered; replacement records a reason and restarts the issuance cycle. Notify members when ready to collect.
- Deliverables: Member profile, expiry job, invoice/verification flow, ID card lifecycle, and audit coverage.

## Phase 8 — Official email, IMAP, templates, and workflow rules

- Integrate the Information Desk mailbox using the approved IMAP/send configuration; show the full email thread on Lead and Member profiles and record all official communication.
- Let Admin/Manager maintain templates and authorized Admins configure bounded workflow rules. Support the required application receipt, pending items, rejection, conversion confirmation, portal activation, ID-ready, renewal-window, expiry, and request outcome notifications.
- Queue delivery through Horizon with retry/failure visibility; prevent duplicate sends on retries and audit message events without exposing credentials.
- Deliverables: mailbox integration, template manager, workflow triggers, delivery monitoring, and email runbook.

## Phase 9 — Read-only member portal, change requests, and renewals

- Implement member login using email or Member ID, Admin/Manager activation and reset, force-change on first login, and the confirmed password policy. Members may change only their password directly.
- Present the approved read-only profile fields, uploaded documents and email thread. Hide internal tags, notes, verification, category, audit timeline, and invoices unless the Phase 1 decision explicitly enables invoice visibility.
- Provide logged member change requests for staff review; profile data changes only after approval. Email changes must use a dedicated request workflow: show requests in the relevant workspace Manager panel, notify the Manager by email, and audit decisions and resulting identity updates. Provide a renewal request from one month before expiry, with staff payment verification. Early renewal begins after the previous term ends; late renewal requires Manager confirmation and begins on that date through the selected term end date. No new membership form is required for renewal.
- Members cannot directly edit profile data, cancel membership, or record attendance.
- Deliverables: portal, account lifecycle, change-request queue, renewal workflow, notifications, and access/privacy review.

## Phase 10 — Dashboard, reports, data tools, and platform settings

- Build dashboards for member/application totals, counts by Lead Status, workspace/Space breakdowns, and ID card states, with filters and saved filters. Managers and delegated staff see only authorized data in their assigned workspace; only Admin/Superadmin can view across workspaces.
- Add field/tag filters, selected-field CSV/Excel exports, and import/mass update as authorized module actions. Zoho import/export must use the same module/action authorization model; Managers can perform it only in their workspace when granted the relevant actions. Standard export is limited to own-Space data. Audit all exports.
- Build Admin settings for staff users/scopes, role and user permissions, member portal access, system log search/export, email templates, workflow rules, and validated operational settings. Logs remain read-only for every role, including Admin.
- Implement scheduled/manual backups on the confirmed schedule and formats, with protected access, retention, restore verification, and audit events.
- Deliverables: dashboards/reports, scoped data tools, Admin settings panel, backup/restore procedures, and operational controls.

## Phase 11 — Zoho migration and reconciliation

- Export Zoho Leads, Contacts/Members, Forms entries, documents, photos, and consent files. Map fields and statuses; preserve Zoho IDs.
- Match attachments to records using Zoho IDs, safely rename/associate them, and validate that every file opens on the correct profile. Reconcile counts by Space and status, unique emails, Member IDs, joining/expiry dates, and derived Active/Inactive status.
- Import portal accounts inactive; verify sample records and exception reports with EMK before cutover. Freeze Zoho Forms at cutover and redirect the website to the new form. Keep a rollback and reconciliation path.
- Deliverables: repeatable import scripts/process, dry-run report, signed reconciliation, cutover checklist, and rollback plan.

## Phase 12 — Verification, security review, release, and later payment gateway

- Verify role/action/Space boundaries, duplicate prevention, form rules, file security, conversion gating, status transitions, invoices, expiry, card workflow, portal read-only behavior, change/renewal approvals, email retries, audit immutability, exports, backups, and migration reconciliation. Include Livewire tampering and direct service/route access scenarios.
- Perform accessibility, responsive, privacy, security, backup restore, and operational readiness reviews. Resolve all release-blocking findings.
- Release through staged environments with migrations, monitoring, Horizon/scheduler checks, named Admin provisioning, recovery access, administrator training, and rollback steps.
- Treat the payment gateway as a later final-stage decision: choose SSLCommerz or bKash, confirm provider requirements, and integrate via a payment adapter for new applications and renewals without changing Lead/Member models.
- Deliverables: acceptance evidence, release sign-off, admin/member guides, rollback plan, and post-release backlog.

## Cross-phase completion criteria

- Every module action has a stable permission identifier and server-side authorization. Managers and delegated staff cannot discover or access another workspace; only Admin/Superadmin can cross workspace boundaries.
- Applicant matching uses verified email. Reapplication after rejection is allowed, and Managers see prior rejection reasons/history. Managers may block an email from future applications.
- Manager-verified bKash payment is required before application approval/conversion and renewal. Conversion also requires the approved document/photo checks; reversal requires a reason and audit trail. Invoice timing follows confirmed accounting requirements.
- Members can view approved profile data and request changes/renewals without directly editing profile data.
- Admin/Superadmin and Manager controls operate through validated workflows and scoped delegation; audit history is append-only and covers all specified sensitive events. Secrets, core authorization, and arbitrary code execution remain outside self-service control.
- Email, Horizon, expiry scheduling, backups, migration, and recovery are observable and operationally recoverable.

## Discovery gaps and decision register

The user answered the initial discovery card. The decisions above are now incorporated. The following gaps remain; do not convert these into assumed requirements. Assign an owner and due date to each deferred item before baselining affected scope, estimates, or cutover.

### Priority 1 — decide or formally defer before estimates

1. **Workspace map and Hub integration (owner: Superadmin):** EMK Center Dhaka is the launch workspace. Superadmin will decide and create workspaces for other Hubs later. Mapping the six named American Spaces to workspaces, whether a workspace contains multiple Spaces, and Hub login/navigation/data conventions remain open.
2. **Application form and statuses:** Confirm exact fields, validation, document/photo verification states and evidence, status dictionaries/transitions, and Member ID format.
3. **Conversion reversal:** Manager may reverse a conversion with a required reason; define reversal state effects and any safeguards. No second-person review is required for payment verification.
4. **Privacy and records (owner: Superadmin):** Under-18 applicants are covered by the stated age rule; Managers may soft-delete and Superadmin may view all logs and hard-delete. Consent wording, file access, retention periods, and safeguards remain open.
5. **Migration (owner: Superadmin):** Superadmin will prepare Zoho source scope, historical data/files, volumes/data quality, reconciliation sign-off, and cutover/rollback details later. Import/export remain permission-controlled module actions.
6. **Operations and acceptance (owner: Superadmin):** Capacity is about 500 members and 5 staff. The user skipped recovery/acceptance details; set support hours, hosting/support ownership, recovery point/time, pilot, measurable scenarios, sign-off, and rollback before release baselining.

### Priority 2 — decide before the relevant phase

- Exact form fields, validation, file/photo constraints, consent behavior, status dictionaries/transitions, City/Place of Study sources, allowed character rules, and Member ID format.
- Whether Managers may approve all member change requests and restrictions/revocations; what global Admin/Superadmin safeguards apply.
- Portal-visible fields, files, messages, and invoices; activation, identity verification, password and account recovery policy.
- Official mailbox, inbound matching, supported languages, template approvers, and notification failure ownership.
- Reports, export fields and formats, approval/reason requirements for sensitive exports, and launch import/mass-update needs.
- Backup schedule/retention, restore verification, infrastructure decisions, and gateway choice/timing.

### Deferred until owner and date are recorded

- **Zoho migration inventory and reconciliation:** Superadmin will prepare scope later; due date not supplied.
- **Privacy/retention/deletion policy:** Superadmin owns the remaining policy; due date not supplied. Manager soft-delete and Superadmin hard-delete authority are recorded, but retention and safeguards remain open.
- **Operational targets, rollout and acceptance sign-off:** Superadmin owns the decisions; due date not supplied. Capacity estimate is 500 members/5 staff; recovery target remains undefined because the user skipped the question.
- **Unanswered specification details:** list above; schedule decisions before the corresponding phase begins.

### Remaining specification details

- Source/ownership of City and Place of Study; accepted character rules; final Lead/Current Status values and transitions; and Member ID format. Under-18 age rule is recorded; consent wording remains open.
- Member change-request approver, portal-visible fields/documents/invoices, password and account recovery policy.
- Hosting/database/queue/cache/storage/mail setup, backup schedule, retention/deletion policy, and later gateway choice (SSLCommerz or bKash). Never put credentials in this plan.

### Phase 1 exit gate

Before baselining scope, dates, or estimates, publish a decision register in which each remaining Priority 1 gap has an owner and due date and is marked **decided**, **deferred**, or **out of scope**. Update workflow diagrams, the workspace-to-Space map (EMK Center Dhaka launch; remaining mapping owned by Superadmin), field/status dictionary, module/action/workspace matrix, migration inventory, privacy/retention rules, and acceptance scenarios. Confirm authorization preserves Manager/staff workspace isolation, Admin/Superadmin cross-workspace access, delegated module/action grants, verified-email matching and email blocks, rejection history on reapplication, email-change requests, and payment-before-approval/renewal. Do not treat skipped operational targets or unresolved privacy/migration details as decided.

