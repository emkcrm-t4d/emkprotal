---
phase: 03-application-foundation-and-deployment-baseline
plan: 04
subsystem: encrypted-private-storage
tags:
  - storage
  - encryption
  - aes-256
  - sha-256
  - uuid-masking
  - tenancy
  - youth-protection
  - audit-logging
requires:
  - 03-03
provides:
  - Private filesystem disk configuration isolated outside public web root
  - StoragePathResolver service with strict anti-traversal defense and UUID assertions
  - DocumentStorageService with cryptographic UUID masking, SHA-256 hashing, and AES-256 encryption at rest
  - Youth protection auto-tagging for applicants under 18 per DEC-09
  - Authenticated DocumentDownloadController enforcing tenancy barriers, Spatie RBAC, and security headers
  - Append-only audit logging of document access events in audit_logs per DEC-11
affects:
  - Phase 04 Identity & RBAC
  - Phase 05 Lead Ingestion & Public Forms
  - Phase 06 Tri-State Lead Verification
tech-stack:
  added: []
  patterns:
    - Encrypted private storage disk outside web root with zero symlinks
    - Cryptographic UUID v4 filename masking preventing direct URL prediction and client filename leakage
    - Transparent AES-256 encryption at rest with SHA-256 pre-encryption integrity verification
    - 7-step defense-in-depth authenticated document streaming with security headers
    - Append-only audit event recording for forensic accountability
key-files:
  created:
    - app/Domain/Leads/Services/StoragePathResolver.php
    - app/Domain/Leads/Services/DocumentStorageService.php
    - app/Domain/Leads/Models/Lead.php
    - app/Domain/Leads/Models/LeadAttachment.php
    - app/Http/Controllers/DocumentDownloadController.php
    - tests/Feature/DocumentStorageTest.php
    - tests/Feature/DocumentDownloadControllerTest.php
  modified:
    - config/filesystems.php
    - routes/web.php
    - app/Models/User.php
decisions:
  - "[Plan 03-04]: Segregate applicant documents on private storage disk outside web root (storage/app/private) per FOUN-03 and DEC-09 (D-09)"
  - "[Plan 03-04]: Mask physical file names using UUID v4 strings, preserving client original filenames exclusively in metadata per ARCH-06"
  - "[Plan 03-04]: Calculate SHA-256 file hashes on upload and encrypt sensitive files at rest using AES-256"
  - "[Plan 03-04]: Require elevated leads.view_youth_records permission or workspace_manager role for minors flagged with youth_protection per DEC-09"
  - "[Plan 03-04]: Record document_accessed event in audit_logs table on every successful stream per DEC-11 (D-11)"
status: complete
actuals:
  tokens: 10830
  tasks: 3
  commits: 3
  plan_head_before: 0ea74d7e612d8f3ad2618f2238c44e878173a02b
  plan_head_after: b16b5b36745aab2d8c733d10ba1ad0b393c75e6e
---

# Phase 3 Plan 04: Encrypted Private File Storage Driver, UUID Cryptographic Masking, and Authenticated Streaming Controller Summary

## Overview

Plan 03-04 implemented the secure document storage and streaming pipeline for the EMK Membership CRM platform in strict accordance with **FOUN-03**, **DEC-09 (D-09)**, **DEC-11 (D-11)**, **ARCH-06**, and **SECURITY-SPEC.md**.

Applicant identity scans, passports, studio portraits, and under-18 parental consent instruments are persisted exclusively to a private storage disk outside the public web root (`storage/app/private/`). Physical filenames are masked with cryptographic UUID v4 strings to prevent client filename leakage and URL prediction. Files are hashed with SHA-256 for integrity verification and encrypted at rest with AES-256. Document downloads are mediated through an authenticated controller enforcing multi-workspace isolation barriers, Spatie RBAC permissions (with elevated compliance gating for youth applicant records), append-only audit logging in `audit_logs`, and strict security streaming headers.

## Key Changes by Task

### Task 1: Private Filesystem Disk Configuration and Safe Path Resolver
- **Private Filesystem Disk (`config/filesystems.php`):**
  - Configured disk `private` with driver `local`, root `storage_path('app/private')`, `visibility => 'private'`, and `throw => true`.
  - Verified that `php artisan storage:link` creates symlinks strictly for `storage/app/public`, maintaining zero direct web access to `storage/app/private`.
- **Safe Path Resolver (`app/Domain/Leads/Services/StoragePathResolver.php`):**
  - Implemented `resolveLeadAttachmentPath`: constructs `workspaces/{workspaceId}/leads/{leadUuid}/{fileUuid}.{extension}`.
  - Implemented `resolvePoolAttachmentPath`: constructs `workspaces/pool/leads/{leadUuid}/{fileUuid}.{extension}` for unassigned staging applications.
  - Implemented `resolveMemberAttachmentPath`: constructs `workspaces/{workspaceId}/members/{memberUuid}/{fileUuid}.{extension}` for member documents.
  - Enforced strict anti-traversal sanitization: rejects path traversal sequences (`..`, `/`, `\`) and null byte injections with `InvalidArgumentException`.
  - Validated UUID format on all lead, member, and file UUID parameters using `Ramsey\Uuid\Uuid::isValid()`.
- Committed atomically in `b8944ed`.

### Task 2: DocumentStorageService with Cryptographic UUID Masking, SHA-256 Hashes, and Encryption
- **DocumentStorageService (`app/Domain/Leads/Services/DocumentStorageService.php`):**
  - Ingests uploaded files (`UploadedFile` instances or raw binary strings).
  - Generates cryptographic UUID v4 filenames (`Str::uuid()->toString() . '.' . $extension`); client-supplied original filename is never used on disk.
  - Computes 64-character SHA-256 integrity hash on the raw unencrypted file content.
  - Encrypts files at rest with AES-256 via `Crypt::encryptString` before persisting to the private disk.
  - Automatically flags `youth_protection = true` when applicant age < 18 or when document type is `parental_consent` per **DEC-09**.
  - Implements atomic write semantics with rollback deletion on upload errors.
  - Implemented `get(string $path)` with transparent decryption and `delete(string $path)` methods.
- **Feature Test Suite (`tests/Feature/DocumentStorageTest.php`):**
  - Tested UUID v4 filename generation and storage on private disk.
  - Tested original filename preservation in returned metadata while physical disk path contains only UUID.
  - Tested SHA-256 hash matching raw unencrypted content.
  - Tested youth protection auto-tagging for applicant ages 16, 17 vs 18, 29.
  - Tested private disk isolation (confirming file is absent from public disk).
  - Tested AES-256 encryption at rest and transparent decryption via `get()`.
  - Tested safe deletion and path traversal rejection.
- Committed atomically in `d3ddf10`.

### Task 3: DocumentDownloadController with Multi-Workspace Isolation, RBAC, and Audit Logging
- **Domain Models & User Enhancements:**
  - `app/Domain/Leads/Models/Lead.php`: Eloquent model with soft deletes, casts, and attachments relation.
  - `app/Domain/Leads/Models/LeadAttachment.php`: Eloquent model with casts and lead relation.
  - `app/Models/User.php`: Added `workspace_id` to mass assignment fillable, added `hasRole()`, `isSuperAdmin()`, and `isGlobalAdmin()` helpers.
- **Route Registration (`routes/web.php`):**
  - Bound `GET /workspaces/{workspace}/documents/{attachment}` to `DocumentDownloadController@download`.
- **Authenticated Streaming Controller (`app/Http/Controllers/DocumentDownloadController.php`):**
  - **Step 1 (Auth):** Asserts authenticated user session; aborts with HTTP 401 on unauthenticated access.
  - **Step 2 (Tenancy):** Verifies user `workspace_id` and route `{workspace}` match `lead.workspace_id`; aborts with HTTP 403 on mismatch (Superadmin and Global Admin bypass).
  - **Step 3 (RBAC):** Checks permissions: if `youth_protection` is true, requires `leads.view_youth_records` permission or `workspace_manager` role; otherwise requires `leads.view_documents` permission. Aborts with HTTP 403 on failure per **DEC-09**.
  - **Step 4 (Disk Existence):** Confirms file existence on `private` disk; aborts with HTTP 404 if missing.
  - **Step 5 (Decryption):** Decrypts payload using `DocumentStorageService`.
  - **Step 6 (Audit Trail):** Appends `document_accessed` event into `audit_logs` table with actor, IP, timestamp, and target metadata per **DEC-11**.
  - **Step 7 (Secure Streaming):** Returns streamed response with security headers: `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-cache, no-store, must-revalidate`, `Pragma: no-cache`, and `Content-Disposition: inline; filename="..."`.
- **Feature Test Suite (`tests/Feature/DocumentDownloadControllerTest.php`):**
  - Tested authorized streaming with 200 response and security headers.
  - Tested cross-workspace staff 403 rejection.
  - Tested staff without youth permission 403 rejection for minor documents.
  - Tested staff with youth permission 200 streaming.
  - Tested workspace manager role 200 streaming for youth documents.
  - Tested immutable audit log recording in `audit_logs` table.
  - Tested unauthenticated 401 rejection.
  - Tested missing file 404 response.
  - Tested Superadmin cross-workspace access.
- Committed atomically in `b16b5b3`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Critical Functionality] Added Lead and LeadAttachment Eloquent domain models**
- **Found during:** Task 3 controller implementation
- **Issue:** `DocumentDownloadController` requires Eloquent relations (`$attachment->lead`) to enforce workspace tenancy barriers, but `Lead` and `LeadAttachment` models were not yet created.
- **Fix:** Implemented `app/Domain/Leads/Models/Lead.php` and `app/Domain/Leads/Models/LeadAttachment.php` conforming to relational schema definitions and Pest Arch encapsulation rules.
- **Files modified:** `app/Domain/Leads/Models/Lead.php`, `app/Domain/Leads/Models/LeadAttachment.php`
- **Commit:** `b16b5b3`

**2. [Rule 1 - Bug] Added explicit `$fillable` property on User model**
- **Found during:** Task 3 feature test execution
- **Issue:** User creation in tests failed with `MassAssignmentException` because `workspace_id` was not registered on the Eloquent model's fillable property.
- **Fix:** Added `protected $fillable` array containing `workspace_id`, `guard`, `is_active`, `must_reset_password`, `name`, `email`, and `password`.
- **Files modified:** `app/Models/User.php`
- **Commit:** `b16b5b3`

**3. [Rule 1 - Bug] Adjusted Cache-Control header assertion in test to match Symfony header normalization**
- **Found during:** Task 3 feature test execution
- **Issue:** Symfony's `ResponseHeaderBag` alphabetically sorts cache-control directives (`must-revalidate, no-cache, no-store, private`), causing strict string equality check on unordered string to fail.
- **Fix:** Updated test assertion to verify each individual security directive (`no-cache`, `no-store`, `private`, `must-revalidate`).
- **Files modified:** `tests/Feature/DocumentDownloadControllerTest.php`
- **Commit:** `b16b5b3`

## Known Stubs

None. All path resolvers, storage services, controllers, routes, Eloquent models, and test suites are fully implemented with real encryption, hashing, and database persistence.

## Threat Flags

None. All threat model mitigations from PLAN.md were implemented and verified:
- `T-03-10`: Private storage outside web root and UUID filename masking prevent URL enumeration and public access.
- `T-03-11`: Multi-workspace isolation barrier and youth protection checks enforce least privilege and compliance.
- `T-03-12`: Pre-encryption SHA-256 hashing and AES-256 encryption at rest protect file integrity and confidentiality.

## Self-Check: PASSED

1. **Created/Modified files exist:**
   - `config/filesystems.php`: FOUND
   - `app/Domain/Leads/Services/StoragePathResolver.php`: FOUND
   - `app/Domain/Leads/Services/DocumentStorageService.php`: FOUND
   - `app/Domain/Leads/Models/Lead.php`: FOUND
   - `app/Domain/Leads/Models/LeadAttachment.php`: FOUND
   - `app/Http/Controllers/DocumentDownloadController.php`: FOUND
   - `routes/web.php`: FOUND
   - `tests/Feature/DocumentStorageTest.php`: FOUND
   - `tests/Feature/DocumentDownloadControllerTest.php`: FOUND
   - `app/Models/User.php`: FOUND

2. **Commits exist in Git history:**
   - `b8944ed`: FOUND
   - `d3ddf10`: FOUND
   - `b16b5b3`: FOUND
