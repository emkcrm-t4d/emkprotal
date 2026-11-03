# Constraints and Specifications

## Technology Stack Baseline
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: nfr
- content:
  The system must be built with Laravel 12, Livewire, Spatie Laravel Permission, Laravel Horizon, Tailwind CSS, and Alpine.js. Application features are deployed inside the existing Hub.

## Multi-Workspace Isolation and Boundary Enforcement
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: nfr
- content:
  The feature provides separate workspaces for offices. The launch workspace is EMK Center Dhaka; the Superadmin will decide and create workspaces for other Hubs later. Workspace Managers and delegated staff cannot discover, view, or access other workspaces. Global Admin and Superadmin hold cross-workspace authority. Workspace isolation and Space scope must be enforced server-side at routes, Livewire actions, and service/domain boundaries for all queries and actions.

## Modular Architecture and Security Boundaries
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: api-contract
- content:
  Modules are supported, versioned application features with declared actions registered in a central registry (Forms, Leads, Members, Member Portal, Invoices, Dashboard/Reports, Platform Services, Migration). Avoid runtime code loading. Superadmins and Managers configure business settings through validated workflows; they cannot change secrets, bypass authorization, edit audit history, or load arbitrary code. Credentials must reside in deployment-managed secret storage.

## Replaceable Payment Provider Boundary
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: api-contract
- content:
  The payment provider integration must be kept replaceable via a payment adapter so that initial manual bKash transaction verification and later gateway integration (SSLCommerz or bKash) do not reshape Lead or Member data models.

## Role-Based Access Control and Delegation Model
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: schema
- content:
  Stable permission identifiers are defined by module and action using Spatie Laravel Permission. Roles include global Admin/Superadmin, workspace Manager, delegated staff, and Member accounts. Managers administer their workspace and delegate staff permissions within their workspace only (actions include view, create, edit, delete, export, import, verify payment, approve, convert, reverse conversion, and manage requests). Invalidate permission caches predictably after updates. Members have no direct data-edit permissions.

## Append-Only Audit Schema and Immutability
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: schema
- content:
  Append-only audit log recording actor, timestamp, target, and old/new values while strictly excluding secrets. Events covered include record changes, status transitions, conversions, conversion reversals, file uploads/replacements, emails, logins and failed logins, password changes/resets, exports/backups, access-right changes, and update/renewal request outcomes. System logs remain read-only for all roles, including Admin.

## Membership Public Application Form 31-Field Contract
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: schema
- content:
  The public membership application form consists of 31 fields and rules:
  1. First Name: required, letters only, Aa.
  2. Middle Name: optional, letters only, Aa.
  3. Last Name: required, letters only, Aa.
  4. American Spaces: required dropdown (American Center, EMK Center, Barishal Corner, Chattogram Corner, Rajshahi Corner, Sylhet Corner).
  5. Sex: required (Male/Female/N/A).
  6. Date of Birth: required MM/DD/YYYY, with age calculation.
  7. Consent Form Upload: conditionally shown and required for applicants under 18 years of age.
  8. House No.: required, any characters.
  9. Road No.: optional, any characters.
  10. Area: required, any characters.
  11. ZIP: required, any characters.
  12. Division: required Bangladesh division dropdown.
  13. District: required dropdown filtered by Division.
  14. Contact Number: required digits in +880XXXXXXXXXX format and configured length.
  15. Email: required valid format, unique across the system.
  16. Website: optional.
  17. Occupation: required (Artist, Community Worker, Educator, Entrepreneur, Media Professional, Performer, Student, Other with text).
  18. Last/current place of study: required, letters only, Aa.
  19. Highest certification: required (SSC, O-level, HSC, A-level, Undergraduate Degree, Undergraduate Honors, Graduate, Postgraduate, Other with text).
  20. Area of Subject: required, letters only, Aa.
  21. U.S. work/study: required Yes/No.
  22. U.S. location: conditional on Yes, letters only, Aa.
  23. American institution alumni: required Yes/No.
  24. American school: conditional on Yes, letters only, Aa.
  25. Current Employer: optional, letters only, Aa.
  26. Title: optional, letters only, Aa.
  27. Work Phone: optional digits in +880XXXXXXXXXX format.
  28. Documents Upload: required, pdf/jpg/jpeg/doc/png, maximum 2 files at 4 MB each.
  29. Photo Upload: required, one jpg/jpeg/png file up to 4 MB (recent studio passport photo: white background, no glasses, ears visible, regular clothes, no selfies).
  30. bKash Transaction ID: required, uppercase letters/numbers, exactly 10 characters.
  31. Terms and Conditions: required checkbox.

## Application Submission and Applicant Matching Protocol
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: protocol
- content:
  Duplicate submissions are not allowed. Submission maps form fields to Lead fields and creates one Lead with attached files and bKash transaction ID. Applicant matching uses verified email. Uploaded files are stored privately with safe system-generated names. Reapplication is permitted after rejection; Managers see prior rejection causes and history. Managers can block an email from future applications.

## Lead Verification Workflow and Conversion Invariant
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: protocol
- content:
  Maintain separate Payment, Document, and Photo verification states with notes and evidence. Manual bKash payments are verified by workspace Managers using the transaction ID; duplicate transaction IDs are rejected with cause; no second-person review required. Conversion Invariant: no conversion until payment, document, and photo verification are all Complete; no duplicate or incomplete application approval. Conversion from Lead to Member must be atomic, copying/matching fields and files, safely associating documents with the new Member ID, and logging an audit event. Conversion reversal requires a reason, permission, and immutable audit event. Invoices are generated only after conversion.

## Membership Lifecycle, Terms, and Expiry Protocol
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: protocol
- content:
  Membership terms and fees are database-configurable records (never hard-coded two-year defaults). Membership start date is set to Manager approval date; end date is calculated from selected term. Renewal window opens one month before term end. Early renewal starts after current term ends. Late renewal requires Manager confirmation and runs from approval date through selected term end date. Renewal requests do not require submitting a new membership form; payment verification is required before renewal confirmation. Statuses include Active, Inactive, Restricted, Revoked. Restriction and revocation require Admin authority, reason, and EMK management instruction. No cancellation action is provided.

## ID Card Issuance Lifecycle
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: protocol
- content:
  ID card issuance is tracked through three sequential states: Provided for Printing -> Card Printed & Ready to Collect -> Delivered. Card replacement records a reason and restarts the issuance cycle. Members receive notification when the card is ready to collect.

## Official Mailbox and Queued Delivery Protocol
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: protocol
- content:
  Integrate Information Desk mailbox using IMAP/send configuration. Display full email threads on Lead and Member profiles and record official communications. Queue delivery through Laravel Horizon with retry and failure visibility; prevent duplicate sends on retries; audit message events without exposing credentials.

## Member Portal Access and Change Request Protocol
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: protocol
- content:
  Member login uses email or Member ID, with forced password change on first login. The portal is read-only for member profile fields, uploaded documents, and email threads. Internal tags, notes, verification states, categories, audit timelines, and invoices remain hidden (unless explicitly enabled). Members cannot directly edit profile data, cancel membership, or log attendance. Members submit logged change requests for staff review; profile updates occur only upon staff approval. Dedicated Email Change workflow displays requests in the workspace Manager panel, sends an email notification to the Manager, and audits decisions and resulting identity updates.

## Data Export, Scoping, and Platform Governance
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: nfr
- content:
  Dashboards and reports scope data by workspace: Managers and delegated staff see only authorized workspace data; cross-workspace data access is reserved for Admin/Superadmin. Data export (CSV/Excel) is an authorized module action restricted to own-Space data. Zoho import/export operates under the same module/action authorization model. All exports and backups are audited. Backups execute on scheduled/manual basis with protected access, encryption, and restore verification.

## Zoho Migration and Reconciliation Protocol
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: protocol
- content:
  Export Zoho Leads, Contacts/Members, Forms entries, documents, photos, and consent files. Map fields and statuses while preserving Zoho record IDs for traceability. Attachments matched to records using Zoho IDs, safely renamed, and verified on profiles. Reconcile counts by Space and status, unique emails, Member IDs, joining/expiry dates, and Active/Inactive statuses. Portal accounts imported in inactive state; dry-run verification required before cutover. Freeze Zoho Forms at cutover and redirect to the new form. Keep a rollback and reconciliation path.

## Capacity and Operational Planning NFR
- source: d:/EMK_Protal/Docs/emk-membership-crm-plan-revision-10.md
- type: nfr
- content:
  Initial planning capacity is approximately 500 members and 5 staff. System must support Horizon queue workers, scheduled tasks, and private document storage with safe system-generated filenames.
