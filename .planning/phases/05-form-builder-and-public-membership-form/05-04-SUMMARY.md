---
phase: 05-form-builder-and-public-membership-form
plan: 04
subsystem: forms-and-lead-ingestion
tags:
  - forms
  - leads
  - bkash
  - duplicate-prevention
  - ingestion
  - file-encryption
  - youth-protection
  - mail
status: complete
requires:
  - "05-01"
  - "05-02"
  - "05-03"
provides:
  - lead-ingestion-pipeline
  - duplicate-submission-guard
  - encrypted-private-storage-handler
  - applicant-confirmation-mail
  - 31-field-store-request
affects:
  - app/Domain/Forms/Services/DuplicateSubmissionGuard.php
  - app/Domain/Forms/Services/LeadIngestionService.php
  - app/Domain/Forms/Mail/ApplicationConfirmationMail.php
  - resources/views/emails/application-confirmation.blade.php
  - app/Http/Requests/StoreMembershipApplicationRequest.php
  - app/Livewire/Forms/PublicMembershipForm.php
  - resources/views/livewire/forms/public-membership-form.blade.php
  - tests/Feature/DuplicateSubmissionGuardTest.php
  - tests/Feature/LeadIngestionServiceTest.php
  - tests/Feature/ApplicationSubmissionNotificationTest.php
tech-stack:
  added: []
  patterns:
    - "System-wide duplicate bKash Transaction ID assertion across leads and invoices tables with exact 10-char uppercase alphanumeric regex validation per DEC-05"
    - "Email uniqueness guard preventing duplicate applications for active/pending applicants while permitting reapplication for rejected applicants per LEAD-07 / FORM-05"
    - "Multi-workspace space routing resolving emk_center to ws-emk-dhaka (EMK-DHK) and remaining 5 Spaces to Superadmin staging pool (workspace_id = null) per DEC-01"
    - "Automated age derivation and conditional youth parental consent enforcement with youth_protection = true auto-flagging on all minor attachments per DEC-09"
    - "Encrypted private filesystem storage with UUID v4 physical filenames, SHA-256 integrity hash verification, and atomic disk cleanup on database failure"
    - "Queued applicant confirmation notification containing tracking reference UUID, selected Space, bKash TxID, and 3-step review timeline"
key-files:
  created:
    - app/Domain/Forms/Services/DuplicateSubmissionGuard.php
    - app/Domain/Forms/Services/LeadIngestionService.php
    - app/Domain/Forms/Mail/ApplicationConfirmationMail.php
    - resources/views/emails/application-confirmation.blade.php
    - app/Http/Requests/StoreMembershipApplicationRequest.php
    - tests/Feature/DuplicateSubmissionGuardTest.php
    - tests/Feature/LeadIngestionServiceTest.php
    - tests/Feature/ApplicationSubmissionNotificationTest.php
  modified:
    - app/Livewire/Forms/PublicMembershipForm.php
    - resources/views/livewire/forms/public-membership-form.blade.php
decisions:
  - "DEC-01 (D-01) Space Mapping: emk_center routes to ws-emk-dhaka with space code EMK-DHK; american_center, barishal_corner, chattogram_corner, rajshahi_corner, and sylhet_corner route to null workspace staging pool with corresponding space codes (AC-DHK, CR-BAR, CR-CTG, CR-RAJ, CR-SYL)."
  - "DEC-05 (D-05) bKash Integrity Guard: Enforced 10-char uppercase alphanumeric regex /^[A-Z0-9]{10}$/ and system-wide cross-table uniqueness against leads.bkash_txid and invoices.payment_reference."
  - "DEC-09 (D-09) Youth Protection & Consent: Enforced mandatory parental consent upload for applicants < 18 years old; automatically flagged youth_protection = true across all minor attachments stored via DocumentStorageService."
  - "LEAD-07 / FORM-05 Reapplication Rule: Enforced email duplicate prevention against active/pending leads (draft, submitted, under_review, missing_items, approved, converted) and users table, while explicitly allowing reapplication for previously rejected applicants."
  - "ASVS V5.2 / T-02-09 Rate Limiting: Protected public form ingestion with 10 submissions/minute per IP rate limiter via Laravel RateLimiter."
  - "FOUN-03 Private File Storage: Stored identity scans, studio photos, and consent forms on encrypted private disk under UUID v4 physical filenames with SHA-256 hashes."
metrics:
  duration: 20m
  completed_date: "2026-11-04"
status: complete
actuals:
  tokens: 17366
  tasks: 3
  commits: 3
  plan_head_before: "91f6afdfaeaff8eff8bd6f56eaba8fdc6cf96314"
  plan_head_after: "a945d036c8453f0be4b7c46e2dd5f53a2bbc4ceb"
---

# Phase 5 Plan 04: Public Ingestion Pipeline, Duplicate Prevention, Encrypted Storage, and Applicant Notification Summary

Secure submission processing, duplicate prevention guards, encrypted private file persistence, Lead domain entity ingestion, and automated queued applicant confirmation notifications per FORM-04, FORM-05, FORM-06, DEC-01 (D-01), DEC-05 (D-05), and DEC-09 (D-09).

## Performance & Execution Overview

- **Tasks Completed:** 3 / 3
- **Test Suite Status:** 208 tests (1245 assertions) passing across the full project test suite; 19 tests (72 assertions) passing across Plan 05-04 feature test suites.
- **Commits:** 3 atomic commits (`0f4b019`, `148896e`, `a945d03`).

## Accomplishments by Task

### Task 1: Duplicate Submission Prevention and Unique bKash / Email Guard
- Implemented `App\Domain\Forms\Services\DuplicateSubmissionGuard`:
  - `assertUniqueBkashTxid(string $txid)`: Normalizes TxID to uppercase trimmed string, asserts `/^[A-Z0-9]{10}$/` format, and checks cross-table uniqueness against `leads.bkash_txid` and `invoices.payment_reference`. Throws `ValidationException` on violation per DEC-05.
  - `assertUniqueEmail(string $email)`: Normalizes email to lowercase, checks `users` table for existing accounts, checks `leads` table for `blocked` status and active/pending applications (`submitted`, `under_review`, `approved`, `converted`, `draft`, `missing_items`). Explicitly permits reapplication for applicants with previous `rejected` status per LEAD-07 / FORM-05.
  - `checkRateLimit(string $ipAddress)`: Enforces max 10 submissions per minute per IP via `RateLimiter` (`membership_submit:{$ipAddress}`), throwing `ThrottleRequestsException` (HTTP 429) per ASVS V5.2 / T-02-09.
- Created feature test `tests/Feature/DuplicateSubmissionGuardTest.php` with 11 automated test cases verifying format validation, cross-table duplicate blocking, rejected reapplicant allowance, blocked lead rejection, and rate limiting.

### Task 2: Lead Ingestion Pipeline and Encrypted Private Storage Handler
- Created `App\Http\Requests\StoreMembershipApplicationRequest`:
  - Server-side validation contract enforcing all 31 fields from `FIELD-DICTIONARY.md` Section 3.
  - Dynamically evaluates applicant age from `date_of_birth` to conditionally require `parental_consent_file` if under 18 per DEC-09.
  - Enforces cascading district restriction based on selected administrative division.
- Created `App\Domain\Forms\Services\LeadIngestionService`:
  - Enforces `DuplicateSubmissionGuard` assertions before database or disk operations.
  - Maps physical American Spaces to workspace tenancy: `emk_center` -> `EMK-DHK` / `ws-emk-dhaka`; remaining 5 American Spaces -> null workspace (Superadmin staging pool) with specific space codes (`AC-DHK`, `CR-BAR`, `CR-CTG`, `CR-RAJ`, `CR-SYL`) per DEC-01.
  - Computes age from DOB and asserts parental consent upload for minors.
  - Persists uploaded files via `DocumentStorageService` to private storage disk with cryptographic UUID v4 filenames, unencrypted SHA-256 integrity hashes, AES-256 encryption at rest, and `youth_protection = true` flagging for minor documents per DEC-09.
  - Executes atomic database transaction: creates `Lead` with all 31 fields and initial verification streams set to `pending`, inserts `LeadAttachment` records, and updates or creates `FormSubmission` with status `'processed'`.
  - Transaction rollback handler: guarantees automatic deletion of stored private disk files if any exception occurs during the database transaction.
- Created feature test `tests/Feature/LeadIngestionServiceTest.php` with 6 automated test cases verifying adult and minor ingestion, staging pool routing, UUID filename obfuscation, SHA-256 integrity, and rollback cleanup.

### Task 3: Applicant Confirmation Notification and Public Submission Wire-Up
- Created queued mailable `App\Domain\Forms\Mail\ApplicationConfirmationMail`:
  - Implements `ShouldQueue` with subject `Your EMK Center Membership Application Received - Ref: {uuid}`.
  - Markdown template at `resources/views/emails/application-confirmation.blade.php` displaying applicant name, reference UUID, selected American Space, bKash TxID, 3-step verification timeline (Payment, Document, Photo), and Information Desk support contacts.
- Wired `submit()` in `PublicMembershipForm.php`:
  - Validates 31 fields against component rules.
  - Enforces IP rate limiting.
  - Ingests application via `LeadIngestionService::ingest()`.
  - Dispatches `ApplicationConfirmationMail` to applicant email via queue.
  - Sets `$submitted = true`, `$is_submitted = true`, `$submission_uuid = $lead->uuid`, `$reference_uuid = $lead->uuid`.
- Enhanced `public-membership-form.blade.php`:
  - Prominent emerald confirmation receipt badge: "Application Submitted Successfully!".
  - Reference number display with one-click copy button.
  - Submission summary details (Applicant name, Email, Selected Space, bKash TxID).
  - Clear expectations notice: "Our verification team will review your payment, documents, and photo within 2-3 business days."
- Created feature test `tests/Feature/ApplicationSubmissionNotificationTest.php` with 2 automated test cases verifying email queue dispatch, markdown rendering, and UI transition.

## Verification Results

```
PASS Tests\Feature\DuplicateSubmissionGuardTest (11 tests, 11 assertions)
PASS Tests\Feature\LeadIngestionServiceTest (6 tests, 44 assertions)
PASS Tests\Feature\ApplicationSubmissionNotificationTest (2 tests, 17 assertions)

Full Test Suite: 208 passed (1245 assertions), 0 failures, 0 regressions.
```

## Known Stubs

None. All 31 form fields are wired from public input to database storage, private disk encryption, and confirmation email dispatch.

## Threat Surface Analysis

All threats identified in PLAN.md `<threat_model>` have been mitigated:
- **T-05-07 (Denial of Service):** Mitigated via `DuplicateSubmissionGuard::checkRateLimit()` enforcing 10 submissions/minute per IP and 4MB file upload caps.
- **T-05-08 (Tampering / Fraud):** Mitigated via `DuplicateSubmissionGuard::assertUniqueBkashTxid()` enforcing 10-char uppercase alphanumeric regex `/^[A-Z0-9]{10}$/` and cross-table uniqueness against `leads` and `invoices`.
- **T-05-SC (File Storage Security):** Mitigated via `DocumentStorageService` enforcing AES-256 encryption at rest, UUID physical filename masking, SHA-256 integrity hashes, and zero public web root exposure.

## Self-Check: PASSED

- [x] `app/Domain/Forms/Services/DuplicateSubmissionGuard.php` exists
- [x] `app/Domain/Forms/Services/LeadIngestionService.php` exists
- [x] `app/Domain/Forms/Mail/ApplicationConfirmationMail.php` exists
- [x] `resources/views/emails/application-confirmation.blade.php` exists
- [x] `app/Http/Requests/StoreMembershipApplicationRequest.php` exists
- [x] `tests/Feature/DuplicateSubmissionGuardTest.php` exists
- [x] `tests/Feature/LeadIngestionServiceTest.php` exists
- [x] `tests/Feature/ApplicationSubmissionNotificationTest.php` exists
- [x] Commit `0f4b019` exists
- [x] Commit `148896e` exists
- [x] Commit `a945d03` exists
- [x] 208 test cases passing with zero regressions
