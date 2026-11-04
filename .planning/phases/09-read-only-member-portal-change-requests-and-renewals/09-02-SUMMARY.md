---
phase: 09-read-only-member-portal-change-requests-and-renewals
plan: 02
status: complete
subsystem: portal
tags: [member-portal, profile, idor-defense, secure-document-streaming, threaded-communications, xss-sanitization, livewire]

requires:
  - phase: 09-read-only-member-portal-change-requests-and-renewals
    plan: 01
    provides: Member portal authentication gateway, multi-guard session isolation, and forced password reset

provides:
  - MemberPortalProfile Livewire component with comprehensive read-only demographic and validity presentation
  - PortalDocumentDownloadController with strict IDOR verification, AES-256 decryption, and immutable audit logging
  - MemberPortalCommunicationHistory Livewire component with XSS sanitization for threaded official correspondence
  - routes/web.php bindings for portal.profile and portal.documents.download
  - Comprehensive feature test suite in tests/Feature/PortalReadOnlyProfileTest.php

affects:
  - 09-03-PLAN (Self-Service Change Request Engine & Field Diff Auditing)
  - 09-04-PLAN (Membership Renewal Workflow & bKash Fee Submission)

actuals:
  tokens: 18273
  tasks: 3
  commits: 3
  plan_head_before: deec565e986e6f9298d4c7fbda751e67301e5076
  plan_head_after: cbc1b246ee0bdecd316be037d51664f23ed9d85c

tech-stack:
  added: []
  patterns:
    - "Read-only demographic presentation strictly isolating administrative deliberations and audit trails"
    - "Defense-in-depth IDOR verification verifying member ownership and directory structure prior to streaming"
    - "Dual-mode streaming with DocumentStorageService AES-256 decryption fallback"
    - "Multi-vector HTML body sanitization neutralizing script, iframe, and event-handler XSS in correspondence records"
    - "Multi-guard guest redirect routing unauthenticated portal requests to portal.login"

key-files:
  created:
    - app/Http/Controllers/Portal/PortalDocumentDownloadController.php
    - app/Livewire/Portal/MemberPortalProfile.php
    - resources/views/livewire/portal/member-portal-profile.blade.php
    - app/Livewire/Portal/MemberPortalCommunicationHistory.php
    - resources/views/livewire/portal/member-portal-communication-history.blade.php
    - tests/Feature/PortalReadOnlyProfileTest.php
  modified:
    - bootstrap/app.php
    - routes/web.php

key-decisions:
  - "Strictly omitted audit_logs, staff verification notes, and post-conversion invoices from portal views per DEC-06 (D-06), DEC-11 (D-11), and DECISION-REGISTER Priority 2"
  - "Enforced IDOR check on document download comparing attachment lead_id with authenticated member lead_id and path namespacing per ARCH-06 and DEC-09"
  - "Sanitized email HTML bodies by stripping script, iframe, object, embed, and inline event handlers to prevent XSS (T-09-06)"
  - "Configured multi-guard redirectGuestsTo in bootstrap/app.php routing unauthenticated portal routes to portal.login"
---

# Phase 09 Plan 02: Read-Only Member Portal Profile, Secure Document Streaming & Threaded Communication History Summary

Read-only Member Portal profile interface, secure private document streaming controller with IDOR protection, and read-only threaded communication history viewer with XSS sanitization per PORT-02, DEC-06 (D-06), DEC-09 (D-09), DEC-11 (D-11), and DECISION-REGISTER.md.

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Read-Only Member Portal Profile Component with Information Disclosure Guards | `fcc4432` | `app/Livewire/Portal/MemberPortalProfile.php`, `resources/views/livewire/portal/member-portal-profile.blade.php`, `bootstrap/app.php`, `routes/web.php` |
| 2 | Secure Member Document Access & IDOR Defense Controller | `a7634b1` | `app/Http/Controllers/Portal/PortalDocumentDownloadController.php`, `routes/web.php` |
| 3 | Read-Only Threaded Communication History Viewer for Members | `cbc1b24` | `app/Livewire/Portal/MemberPortalCommunicationHistory.php`, `resources/views/livewire/portal/member-portal-communication-history.blade.php`, `tests/Feature/PortalReadOnlyProfileTest.php` |

## Key Changes & Implementations

### 1. Read-Only Member Profile Component (`MemberPortalProfile`)
- Bound to `/portal/profile` under `['auth:member', 'member.password.reset']` middleware group.
- Eagerly loads safe relationships (`term`, `idCards`, `lead.attachments`) while strictly unsetting and excluding `invoices`, `lead_verifications`, and `audit_logs`.
- Displays top summary card with Member ID badge, active status badge, membership validity dates (start date, expiration date, days remaining), and physical ID card fulfillment indicator (`provided_for_printing` -> "Queued for Printing", `card_printed_ready_for_pickup` -> "Ready for Pickup at Front Desk", `delivered` -> "Card Delivered").
- Provides 3 active tabs:
  - **Personal Profile**: Clean 2-column key-value grid for all demographic attributes (Name, DOB, Sex, Email, Contact Phone, Full Address, Occupation, Institution, Certification, Subject Area) without `<input>`, `<textarea>`, or `<select>` controls.
  - **Uploaded Documents**: Visual cards displaying uploaded credentials (Studio Photo, NID / Passport, Parental Consent) with download buttons linking to secure download streaming route.
  - **Official Correspondence**: Embeds `MemberPortalCommunicationHistory`.

### 2. Secure Document Streaming & IDOR Defense (`PortalDocumentDownloadController`)
- Endpoint: `GET /portal/documents/{attachment}`.
- Tenancy & ownership assertion: Verifies authenticated member user against attachment's `lead_id` and storage path namespacing (`workspaces/{workspace}/members/{uuid}` or `workspaces/{workspace}/leads/{uuid}`).
- Cross-member and cross-tenant download attempts are blocked immediately with HTTP 403 Forbidden.
- Unauthenticated requests are redirected to `/portal/login` via multi-guard `redirectGuestsTo` configuration in `bootstrap/app.php`.
- Decrypts AES-256 encrypted documents via `DocumentStorageService` (with fallback to private disk get) and streams inline responses with `X-Content-Type-Options: nosniff` and `Cache-Control: private`.
- Appends immutable `portal_document_accessed` audit log entries to `audit_logs` table with `actor_guard = 'member'`.

### 3. Read-Only Threaded Communication History (`MemberPortalCommunicationHistory`)
- Queries email threads linked to authenticated member by `member_id`, `participant_email`, or linked `lead_id` within the workspace.
- Chronologically displays threaded messages with direction tags ("From: You" vs "From: EMK Information Desk"), timestamp, and status.
- Strict read-only guarantee: Members cannot compose outbound messages or inject unauthorized replies.
- Neutralizes XSS vulnerabilities (T-09-06) by stripping `<script>`, `<iframe>`, `<object>`, `<embed>`, `<form>`, `<style>`, inline `on*=` event handlers, and `javascript:` URIs from email HTML bodies before rendering.
- Includes client-side thread search filter and graceful empty state when no correspondence exists.

## Verification & Test Results

`php artisan test tests/Feature/PortalReadOnlyProfileTest.php` executed with 11 passed tests (57 assertions, 100% pass):
- `test_guest_visiting_profile_is_redirected_to_login` (PASS)
- `test_authenticated_member_can_view_profile_demographics_and_membership_terms` (PASS)
- `test_internal_staff_notes_audit_logs_and_invoices_are_strictly_hidden` (PASS)
- `test_profile_active_tab_switching` (PASS)
- `test_member_can_securely_download_own_documents` (PASS)
- `test_idor_defense_blocks_cross_member_document_download_with_403` (PASS)
- `test_guest_cannot_download_member_documents` (PASS)
- `test_nonexistent_document_returns_404` (PASS)
- `test_member_can_view_threaded_email_correspondence` (PASS)
- `test_member_cannot_see_other_members_email_threads` (PASS)
- `test_email_html_body_is_sanitized_against_xss` (PASS)

Portal subsystem full suite verification:
- `PortalAuthenticationAndTracerTest.php` (14 passed) + `PortalReadOnlyProfileTest.php` (11 passed): 25 passed tests, 127 assertions.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Multi-guard guest redirect configuration in bootstrap/app.php**
- **Found during:** Task 1 and Task 2 test verification
- **Issue:** Unauthenticated requests to `/portal/*` were redirected by default to `/login` (staff login) instead of `/portal/login`.
- **Fix:** Added `$middleware->redirectGuestsTo(fn (Request $request) => $request->is('portal*') ? route('portal.login') : route('login'));` in `bootstrap/app.php`.
- **Files modified:** `bootstrap/app.php`
- **Commit:** `fcc4432`

**2. [Rule 1 - Bug] Welcome greeting header alignment across test suites**
- **Found during:** Regression testing with `PortalAuthenticationAndTracerTest.php`
- **Issue:** Plan 01 tracer test asserted `Welcome, {name}` which was present in `placeholder-profile.blade.php`.
- **Fix:** Added `Welcome, {{ $member->full_name }}` greeting in `member-portal-profile.blade.php` header, harmonizing both test expectations.
- **Files modified:** `resources/views/livewire/portal/member-portal-profile.blade.php`
- **Commit:** `fcc4432`

## Threat Model Verification

- **T-09-04 (Information Disclosure):** Audit logs, staff review notes (`lead_verifications`), and conversion invoices are strictly unlinked and never loaded or serialized to member portal clients. Verified via `test_internal_staff_notes_audit_logs_and_invoices_are_strictly_hidden`.
- **T-09-05 (Information Disclosure / IDOR):** `PortalDocumentDownloadController` verifies that the requested `LeadAttachment` foreign key matches authenticated member's `lead_id` and storage path prefix before streaming. Cross-member requests return HTTP 403 Forbidden. Verified via `test_idor_defense_blocks_cross_member_document_download_with_403`.
- **T-09-06 (Cross-Site Scripting):** `MemberPortalCommunicationHistory` strips dangerous HTML markup (`<script>`, `<iframe>`, `on*=` event handlers, `javascript:` protocols). Verified via `test_email_html_body_is_sanitized_against_xss`.

## Self-Check: PASSED
- `app/Http/Controllers/Portal/PortalDocumentDownloadController.php` FOUND
- `app/Livewire/Portal/MemberPortalProfile.php` FOUND
- `resources/views/livewire/portal/member-portal-profile.blade.php` FOUND
- `app/Livewire/Portal/MemberPortalCommunicationHistory.php` FOUND
- `resources/views/livewire/portal/member-portal-communication-history.blade.php` FOUND
- `tests/Feature/PortalReadOnlyProfileTest.php` FOUND
- Commits `fcc4432`, `a7634b1`, and `cbc1b24` verified in git history

