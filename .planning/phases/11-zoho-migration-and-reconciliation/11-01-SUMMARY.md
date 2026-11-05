---
phase: 11-zoho-migration-and-reconciliation
plan: "01"
subsystem: migration
tags:
  - zoho-ingestion
  - staging-tables
  - field-mapping
  - artisan-cli
  - memory-bounds
  - tenancy-defense
dependency_graph:
  requires:
    - 01-discovery-decisions-and-acceptance-criteria
    - 02-architecture-data-model-and-security-design
    - 04-identity-access-control-space-scope-and-audit-core
    - 06-leads-verification-and-controlled-conversion
    - 07-member-profiles-invoices-and-id-cards
  provides:
    - zoho-staging-tables
    - streaming-csv-ingestion-service
    - zoho-field-mapping-dictionary
    - artisan-zoho-import-command
  affects:
    - app/Providers/AppServiceProvider.php
tech_stack:
  added: []
  patterns:
    - streaming-generator-csv-parser
    - chunked-database-transactions
    - staging-to-core-domain-hydration
    - tenancy-isolation
    - immutable-audit-logging
key_files:
  created:
    - database/migrations/2026_11_05_000002_create_zoho_staging_tables.php
    - app/Domain/Migration/Models/ZohoImportBatch.php
    - app/Domain/Migration/Models/ZohoStagingRecord.php
    - app/Domain/Migration/Services/ZohoFieldMappingService.php
    - app/Domain/Migration/Services/ZohoDataIngestionService.php
    - app/Domain/Migration/Commands/ZohoImportCommand.php
    - tests/Feature/ZohoDataIngestionTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Providers/AppServiceProvider.php
decisions:
  - "Used 2026_11_05_000002 for staging tables migration to avoid collision with 2026_11_05_000001_create_platform_settings_table."
  - "Implemented streaming generator pattern (fgetcsv) with explicit gc_collect_cycles() after each chunk to ensure peak memory consumption strictly obeys the 64MB envelope per T-11-02."
  - "Preserved original zoho_id column across leads, members, and users models, preventing duplicate re-ingestion and enabling end-to-end reconciliation."
  - "Enforced 10-char uppercase alphanumeric normalization on legacy bKash TxIDs per DEC-05."
  - "Added immutable append-only audit event 'zoho_batch_imported' capturing batch UUID, source type, and row metrics per DEC-11."
metrics:
  duration: 18 min
  completed_date: "2026-11-05"
status: complete
actuals:
  tokens: 28500
  tasks: 2
  commits: 2
  plan_head_before: "06eda2b6a5aeb45b7f2ce5c24e93c126d0bb47b8"
  plan_head_after: "bee9273c52a321949392e213bf9d9dcbb7ba7cb6"
---

# Phase 11 Plan 01: Zoho Data Ingestion and Staging Summary

**High-throughput stream-chunked Zoho data ingestion engine, staging database isolation, legacy field mapping dictionary, and Artisan CLI command with progress tracking, memory safety under 64MB, and zoho_id preservation.**

---

## Executive Summary

Phase 11 Plan 01 established the core migration infrastructure to ingest, validate, and hydrate legacy Zoho CRM Contacts/Members and Leads into the EMK Portal per **MIGR-01**, **MIGR-02**, **DEC-01 (D-01)**, **DEC-05 (D-05)**, and **DEC-11 (D-11)**.

The system introduces dedicated staging tables (`zoho_import_batches`, `zoho_staging_records`) that isolate untrusted legacy CSV payloads prior to hydrating core domain entities. Ingestion utilizes PHP generators to stream rows without buffering entire files into memory, keeping memory consumption well below the 64MB ceiling even under high volume. Original Zoho identifiers (`zoho_id`) are preserved and indexed across `members`, `leads`, and `users` to provide cross-system traceability and prevent duplicate imports.

---

## Tasks Completed

| Task | Name | Commit | Key Files |
| --- | --- | --- | --- |
| 1 | End-to-End Zoho Staging Schema, Field Mapping Engine, and Ingestion Tracer Slice | `e172e5d` | `database/migrations/2026_11_05_000002_create_zoho_staging_tables.php`<br>`app/Domain/Audit/Models/AuditLog.php`<br>`app/Domain/Migration/Models/ZohoImportBatch.php`<br>`app/Domain/Migration/Models/ZohoStagingRecord.php`<br>`app/Domain/Migration/Services/ZohoFieldMappingService.php`<br>`app/Domain/Migration/Services/ZohoDataIngestionService.php`<br>`tests/Feature/ZohoDataIngestionTest.php` |
| 2 | Artisan CLI Ingestion Command with Chunking, Progress Tracking, and Memory Safety | `bee9273` | `app/Domain/Migration/Commands/ZohoImportCommand.php`<br>`app/Providers/AppServiceProvider.php`<br>`tests/Feature/ZohoDataIngestionTest.php` |

---

## Architectural & Security Highlights

1. **Staging Schema & Isolation (`zoho_import_batches`, `zoho_staging_records`):**
   - Batches record lifecycle timestamps, row counters (`total_rows`, `processed_rows`, `successful_rows`, `skipped_rows`, `failed_rows`), execution status (`pending`, `processing`, `completed`, `failed`), and error summaries.
   - Staging records isolate raw payloads as JSON alongside mapped payloads, validation statuses (`valid`, `warning`, `invalid`), validation errors, and imported entity references.
   - Both models implement `TenantEntityInterface` and use `BelongsToTenant`.

2. **Field Mapping & Legacy Transformation (`ZohoFieldMappingService`):**
   - **Zoho ID:** Extracts and preserves legacy IDs (`CONTACTID`, `LEADID`, `Record Id`) into `zoho_id`.
   - **Phone Normalization:** Sanitizes Bangladeshi numbers into canonical `+8801[3-9]\d{8}` format.
   - **Multi-Format Date Parsing:** Parses varying legacy date representations (`Y-m-d`, `d/m/Y`, `m/d/Y`, `d-M-Y`, etc.) into ISO date strings.
   - **Sex / Gender:** Normalizes diverse text entries into enum (`male`, `female`, `na`).
   - **Dynamic Term Resolution:** Dynamically maps membership plans to `membership_terms` by code, name, or duration.
   - **bKash Normalization:** Enforces 10-character uppercase alphanumeric formatting per DEC-05.
   - **Input Sanitization:** Strips tags, null bytes, and script injection payloads per T-11-01.

3. **Memory Safety & Streaming Engine (`ZohoDataIngestionService`):**
   - Implements PHP generator `streamCsvRows()` using `fopen()` and `fgetcsv()`.
   - Processes data in configurable chunks (default 100 rows) with individual database transactions.
   - Calls `gc_collect_cycles()` after each chunk, maintaining memory peak well under 64MB (asserted in tests) per T-11-02.
   - Detects duplicate `zoho_id` or existing emails, marking records as `skipped` without aborting batch processing.
   - Supports dry-run execution (`--dry-run`) to validate and stage payloads without committing to core tables.

4. **Artisan CLI Console (`ZohoImportCommand`):**
   - Command signature: `zoho:import {file} {--workspace=ws-emk-dhaka} {--type=contacts_members} {--chunk=100} {--dry-run}`.
   - Enforces workspace existence check against `workspaces` table per T-11-03.
   - Renders interactive console progress bar during chunk processing.
   - Outputs consolidated summary table detailing execution metrics, status, and memory peak upon completion.

5. **Immutable Audit Trail (`AuditLog`):**
   - Emits `zoho_batch_imported` audit log entry upon batch completion capturing batch UUID, source type, row counts, and execution parameters per DEC-11.

---

## Verification & Test Results

Command:
```bash
php artisan test tests/Feature/ZohoDataIngestionTest.php
```

Output:
```
   PASS  Tests\Feature\ZohoDataIngestionTest
  ✓ staging tables and models have bidirectional relationships                                                   0.61s  
  ✓ field mapping transforms contacts members fidelity                                                           0.09s  
  ✓ field mapping transforms leads with bkash and status                                                         0.09s  
  ✓ ingest contacts csv persists members and preserves zoho id                                                   0.12s  
  ✓ ingest leads csv persists leads and preserves zoho id                                                        0.10s  
  ✓ duplicate records are skipped without crashing batch                                                         0.10s  
  ✓ dry run stages records without persisting domain models                                                      0.09s  
  ✓ artisan zoho import command executes successfully                                                            0.24s  
  ✓ artisan zoho import command fails gracefully when file missing or invalid                                    0.10s  
  ✓ artisan zoho import command rejects non existent workspace                                                   0.10s  
  ✓ artisan zoho import command maintains memory bounds under multi chunk load                                   1.26s  

  Tests:    11 passed (75 assertions)
  Duration: 3.10s
```

All 11 architecture tests in `tests/Arch/` also pass with 100% compliance.

---

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Explicit Status Overwrite in Field Mapping**
- **Found during:** Task 1 test run.
- **Issue:** `ZohoFieldMappingService` auto-expired members whose `Expiry Date` was in the past regardless of whether raw status was explicitly provided as `Active`.
- **Fix:** Preserved explicit `Status` from legacy export when non-empty, reserving automatic expiry fallback exclusively for records lacking an explicit status value.
- **Files modified:** `app/Domain/Migration/Services/ZohoFieldMappingService.php`.

**2. [Rule 3 - Blocking Issue] Migration Timestamp Collision**
- **Found during:** Migration file creation.
- **Issue:** Timestamp `2026_11_05_000001` was already allocated to `create_platform_settings_table.php`.
- **Fix:** Used `2026_11_05_000002_create_zoho_staging_tables.php` per plan instructions.
- **Files created:** `database/migrations/2026_11_05_000002_create_zoho_staging_tables.php`.

---

## Self-Check: PASSED

1. Created files exist:
   - `database/migrations/2026_11_05_000002_create_zoho_staging_tables.php`: FOUND
   - `app/Domain/Migration/Models/ZohoImportBatch.php`: FOUND
   - `app/Domain/Migration/Models/ZohoStagingRecord.php`: FOUND
   - `app/Domain/Migration/Services/ZohoFieldMappingService.php`: FOUND
   - `app/Domain/Migration/Services/ZohoDataIngestionService.php`: FOUND
   - `app/Domain/Migration/Commands/ZohoImportCommand.php`: FOUND
   - `tests/Feature/ZohoDataIngestionTest.php`: FOUND

2. Commits exist:
   - `e172e5d`: FOUND (feat(phase-11): zoho staging schema and core data ingestion tracer engine)
   - `bee9273`: FOUND (feat(phase-11): artisan zoho import command with progress tracking and memory bounds)
