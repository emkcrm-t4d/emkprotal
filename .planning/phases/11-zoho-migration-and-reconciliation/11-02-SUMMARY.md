---
phase: 11-zoho-migration-and-reconciliation
plan: "02"
subsystem: migration
tags:
  - zoho-attachments
  - path-traversal-defense
  - private-storage
  - aes-256-encryption
  - uuid-masking
  - youth-protection
  - exif-stripping
  - magic-bytes
  - artisan-cli
dependency_graph:
  requires:
    - 01-discovery-decisions-and-acceptance-criteria
    - 02-architecture-data-model-and-security-design
    - 06-leads-verification-and-controlled-conversion
    - 07-member-profiles-invoices-and-id-cards
    - "11-01"
  provides:
    - zoho-attachment-migration-service
    - artisan-zoho-import-attachments-command
    - path-traversal-defense-engine
  affects:
    - app/Providers/AppServiceProvider.php
    - app/Domain/Audit/Models/AuditLog.php
tech_stack:
  added: []
  patterns:
    - path-traversal-and-null-byte-defense
    - magic-bytes-and-mime-whitelist
    - gd-exif-stripping
    - aes-256-at-rest-encryption
    - uuid-v4-filename-masking
    - immutable-audit-logging
key_files:
  created:
    - app/Domain/Migration/Exceptions/SecurityException.php
    - app/Domain/Migration/Services/ZohoAttachmentMigrationService.php
    - app/Domain/Migration/Commands/ZohoImportAttachmentsCommand.php
    - tests/Feature/ZohoAttachmentMigrationTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Providers/AppServiceProvider.php
decisions:
  - "Enforced defense-in-depth path traversal checks rejecting '..', null bytes, and non-whitelisted extensions, followed by canonical realpath boundary assertion against the base attachments directory per T-11-04."
  - "Masked all legacy attachment filenames with UUID v4 outside the web root on the private disk, storing them AES-256 encrypted at rest via DocumentStorageService per ARCH-06 and DEC-09."
  - "Stripped EXIF metadata from JPEGs using GD to eliminate location data and camera hardware fingerprints."
  - "Automatically applied youth_protection = true to applicants under 18 or parental_consent documents per DEC-09."
  - "Emitted individual and batch append-only audit events ('zoho_attachment_migrated' and 'zoho_attachments_batch_imported') per DEC-11."
metrics:
  duration: 15 min
  completed_date: "2026-11-05"
status: complete
actuals:
  tokens: 18500
  tasks: 2
  commits: 2
  plan_head_before: "dcd71a65f4f15ec9435098180ba0c96f2db0a74f"
  plan_head_after: "dd122b3a4f450011cce456792d8699ff11204b25"
---

# Phase 11 Plan 02: Zoho Attachment Migration and Path Traversal Defense Summary

**Secure identity document and studio photo migration service, path traversal and null byte defenses, AES-256 private disk encryption with UUID v4 masking, EXIF metadata stripping, youth protection enforcement, and CLI batch manifest processor.**

---

## Executive Summary

Phase 11 Plan 02 established the file security, cryptographic storage, and batch migration capabilities required to securely migrate legacy Zoho attachments into the EMK Portal per **MIGR-03**, **ARCH-06**, **DEC-09 (D-09)**, and **DEC-11 (D-11)**.

Untrusted legacy files are defended against directory traversal (`..`, `.\`, leading slashes), null-byte injections (`\0`, `%00`), prohibited execution extensions (`.php`, `.exe`, `.sh`, `.blade.php`), and MIME-type spoofing via file header magic-byte verification. Clean files have their EXIF metadata stripped, are hashed with SHA-256, masked with UUID v4 physical filenames outside the web root on the `private` filesystem disk, and encrypted at rest with AES-256 via `DocumentStorageService`. Attachments for applicants under 18 or parental consent documents are automatically flagged for minor privacy protection.

The solution is orchestrated via Artisan CLI command `zoho:import-attachments`, which parses CSV manifests, maps legacy Zoho IDs to imported `Member` or `Lead` profiles, and produces consolidated audit logs.

---

## Tasks Completed

| Task | Name | Commit | Key Files |
| --- | --- | --- | --- |
| 1 | Secure Attachment Resolution, Path Traversal Defense, and Private Disk Storage Engine | `b8d0717` | `app/Domain/Migration/Exceptions/SecurityException.php`<br>`app/Domain/Migration/Services/ZohoAttachmentMigrationService.php`<br>`app/Domain/Audit/Models/AuditLog.php`<br>`tests/Feature/ZohoAttachmentMigrationTest.php` |
| 2 | CLI Attachment Migration Command, Manifest Batch Processor, and Member/Lead Attachment Association | `dd122b3` | `app/Domain/Migration/Commands/ZohoImportAttachmentsCommand.php`<br>`app/Providers/AppServiceProvider.php`<br>`tests/Feature/ZohoAttachmentMigrationTest.php` |

---

## Architectural & Security Highlights

1. **Path Traversal & Injection Defenses (`ZohoAttachmentMigrationService`):**
   - Method `sanitizeRelativePath()` inspects inputs for null-byte injections (raw and URL-encoded `%00`), relative path sequences (`..`, `./`, `.\`), absolute paths, and Windows drive specifications.
   - Asserts that canonical `realpath()` starts strictly within the designated source directory boundary, throwing `SecurityException` if violated (T-11-04).
   - Verifies file extensions against a strict whitelist (`jpg`, `jpeg`, `png`, `pdf`) and explicitly bans dangerous executables (`.php`, `.exe`, `.sh`, `.blade.php`, etc.).

2. **Magic Byte & MIME Type Whitelisting (T-11-05):**
   - Validates file payloads via PHP `finfo` against strictly allowed MIME types (`image/jpeg`, `image/png`, `application/pdf`).
   - Validates binary magic byte headers (`FF D8 FF` for JPEG, `89 50 4E 47` for PNG, `%PDF-` for PDF), thwarting extension-spoofed malicious files.

3. **Privacy Safeguards & AES-256 Storage (ARCH-06, DEC-09, T-11-06):**
   - Re-encodes JPEG images with GD to strip EXIF metadata, eliminating GPS coordinates and camera hardware identifiers.
   - Delegates at-rest encryption to `DocumentStorageService::store()`, writing AES-256 encrypted payloads to `storage/app/private` with random UUID v4 filenames (`{uuid}.{ext}`). Original filenames remain isolated in database metadata.
   - Evaluates applicant age: applicants < 18 or document type `parental_consent` automatically receive `youth_protection = true`.

4. **Target Entity Linking:**
   - Method `migrateAttachment()` looks up `zoho_id` in `members` first, obtaining the parent lead (`$member->lead_id`), then checks `leads`.
   - Creates `lead_attachments` records linked to the corresponding `lead_id` and records SHA-256 file integrity hashes.

5. **Artisan CLI Manifest Processor (`ZohoImportAttachmentsCommand`):**
   - Registered signature: `zoho:import-attachments {directory} {--manifest=} {--workspace=ws-emk-dhaka} {--dry-run}`.
   - Parses manifest CSV, maps headers (`zoho_id`, `filename`, `document_type`), and renders Symfony console progress bar.
   - Gracefully reports missing files and unmapped orphan Zoho IDs without aborting the batch run.
   - Supports dry-run validation mode (`--dry-run`) without modifying the database or disk.
   - Emits consolidated `zoho_attachments_batch_imported` audit log entry with detailed execution metrics.

---

## Verification & Test Results

### 1. Attachment Migration & Security Test Suite
Command:
```bash
php artisan test tests/Feature/ZohoAttachmentMigrationTest.php
```

Output:
```
   PASS  Tests\Feature\ZohoAttachmentMigrationTest
  ✓ valid attachment migration for lead and member                                                               0.61s  
  ✓ files are stored encrypted on private disk with uuid filenames                                               0.11s  
  ✓ path traversal attacks are trapped and rejected                                                              0.08s  
  ✓ windows path traversal attacks are trapped and rejected                                                      0.09s  
  ✓ null byte injection is trapped and rejected                                                                  0.08s  
  ✓ url encoded null byte is trapped and rejected                                                                0.08s  
  ✓ absolute path traversal is rejected                                                                          0.08s  
  ✓ executable file uploads are rejected                                                                         0.09s  
  ✓ spoofed file extension fails magic bytes check                                                               0.09s  
  ✓ youth protection flag is accurately assigned to minors                                                       0.10s  
  ✓ unmapped zoho id throws invalid argument exception                                                           0.10s  
  ✓ artisan zoho import attachments command executes successfully                                                0.15s  
  ✓ artisan zoho import attachments command handles missing manifest or directory                                0.09s  
  ✓ artisan zoho import attachments dry run mode does not persist                                                0.11s  
  ✓ artisan zoho import attachments rejects missing required headers                                             0.10s  

  Tests:    15 passed (60 assertions)
  Duration: 2.19s
```

### 2. Regression Test Suite
Command:
```bash
php artisan test tests/Feature/ZohoDataIngestionTest.php
```

Output:
```
   PASS  Tests\Feature\ZohoDataIngestionTest
  ✓ staging tables and models have bidirectional relationships                                                   0.60s  
  ✓ field mapping transforms contacts members fidelity                                                           0.08s  
  ✓ field mapping transforms leads with bkash and status                                                         0.08s  
  ✓ ingest contacts csv persists members and preserves zoho id                                                   0.12s  
  ✓ ingest leads csv persists leads and preserves zoho id                                                        0.10s  
  ✓ duplicate records are skipped without crashing batch                                                         0.10s  
  ✓ dry run stages records without persisting domain models                                                      0.09s  
  ✓ artisan zoho import command executes successfully                                                            0.12s  
  ✓ artisan zoho import command fails gracefully when file missing or invalid                                    0.09s  
  ✓ artisan zoho import command rejects non existent workspace                                                   0.08s  
  ✓ artisan zoho import command maintains memory bounds under multi chunk load                                   1.24s  

  Tests:    11 passed (75 assertions)
  Duration: 2.96s
```

### 3. Architecture Tests
Command:
```bash
php artisan test tests/Arch
```

Output:
```
  Tests:    11 passed (70 assertions)
  Duration: 7.86s
```

---

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] User Model Factory Missing email_verified_at Column**
- **Found during:** Task 1 test run.
- **Issue:** `User::factory()->create()` failed in SQLite memory database because the application's customized `users` table does not contain `email_verified_at`.
- **Fix:** Instantiated `User::create([...])` with explicit valid columns matching the application's custom schema.
- **Files modified:** `tests/Feature/ZohoAttachmentMigrationTest.php`.
- **Commit:** `b8d0717`.

---

## Self-Check: PASSED

1. Created files exist:
   - `app/Domain/Migration/Exceptions/SecurityException.php`: FOUND
   - `app/Domain/Migration/Services/ZohoAttachmentMigrationService.php`: FOUND
   - `app/Domain/Migration/Commands/ZohoImportAttachmentsCommand.php`: FOUND
   - `tests/Feature/ZohoAttachmentMigrationTest.php`: FOUND
   - `.planning/phases/11-zoho-migration-and-reconciliation/11-02-SUMMARY.md`: FOUND

2. Commits exist:
   - `b8d0717`: FOUND (feat(phase-11): secure attachment migration service with path traversal defense)
   - `dd122b3`: FOUND (feat(phase-11): artisan zoho import-attachments command with manifest processor)
