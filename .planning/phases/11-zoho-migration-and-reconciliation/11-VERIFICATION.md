---
phase: 11-zoho-migration-and-reconciliation
verified: 2026-11-05T07:50:00Z
status: passed
score: 29/29 must-haves verified
covered_files:
  - .planning/phases/11-zoho-migration-and-reconciliation/11-01-PLAN.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-01-SUMMARY.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-02-PLAN.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-02-SUMMARY.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-03-PLAN.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-03-SUMMARY.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-04-PLAN.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-04-SUMMARY.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-05-PLAN.md
  - .planning/phases/11-zoho-migration-and-reconciliation/11-05-SUMMARY.md
  - .planning/phases/11-zoho-migration-and-reconciliation/CUTOVER-RUNBOOK.md
  - app/Domain/Migration/Commands/ZohoDryRunCommand.php
  - app/Domain/Migration/Commands/ZohoExportHubDeltaCommand.php
  - app/Domain/Migration/Commands/ZohoImportAttachmentsCommand.php
  - app/Domain/Migration/Commands/ZohoImportCommand.php
  - app/Domain/Migration/Commands/ZohoProvisionPortalAccountsCommand.php
  - app/Domain/Migration/Commands/ZohoReconcileCommand.php
  - app/Domain/Migration/DTOs/ReconciliationReportDTO.php
  - app/Domain/Migration/Exceptions/SecurityException.php
  - app/Domain/Migration/Models/ZohoImportBatch.php
  - app/Domain/Migration/Models/ZohoStagingRecord.php
  - app/Domain/Migration/Services/HubDeltaExportService.php
  - app/Domain/Migration/Services/PortalAccountProvisioningService.php
  - app/Domain/Migration/Services/ReconciliationReportGenerator.php
  - app/Domain/Migration/Services/ZohoAttachmentMigrationService.php
  - app/Domain/Migration/Services/ZohoDataIngestionService.php
  - app/Domain/Migration/Services/ZohoDryRunEngine.php
  - app/Domain/Migration/Services/ZohoFieldMappingService.php
  - app/Domain/Migration/Services/ZohoReconciliationService.php
  - app/Domain/Platform/Registry/ModuleRegistry.php
  - app/Livewire/Migration/ReconciliationDashboard.php
  - database/migrations/2026_11_05_000002_create_zoho_staging_tables.php
  - resources/views/livewire/migration/reconciliation-dashboard.blade.php
  - routes/web.php
  - tests/Arch/Phase11ArchitectureTest.php
  - tests/Feature/EndToEndZohoMigrationAndReconciliationTest.php
  - tests/Feature/ZohoAttachmentMigrationTest.php
  - tests/Feature/ZohoDataIngestionTest.php
  - tests/Feature/ZohoDryRunAndCutoverTest.php
  - tests/Feature/ZohoReconciliationReportTest.php
covered_digest: "v4:sha256:b993dbfc13ffc52ff185d8ef3d54054bedee4c3f97cc67ad58bd2a087efe3a46"
behavior_unverified: 0
overrides_applied: 0
---

# Phase 11: Zoho Migration and Reconciliation Verification Report

**Phase Goal:** Legacy Zoho data and documents are imported, safely associated, and verified with zero reconciliation discrepancies.  
**Verified:** 2026-11-05T07:50:00Z  
**Status:** passed  
**Re-verification:** No — initial verification  

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|---|---|---|
| 1 | Migration pipeline imports legacy Zoho Leads, Contacts/Members, and Form entries while preserving original Zoho record IDs (`zoho_id`). | ✓ VERIFIED | `ZohoDataIngestionService` and `ZohoImportCommand` stream CSV rows, populate `zoho_staging_records`, and hydrate `Lead` / `Member` records indexing `zoho_id`. Verified in `ZohoDataIngestionTest` and E2E Scenario 2. |
| 2 | Legacy identity documents and photos are safely matched, renamed, and verified on imported profiles. | ✓ VERIFIED | `ZohoAttachmentMigrationService` and `ZohoImportAttachmentsCommand` match attachments via manifest to `zoho_id`, store encrypted files on private disk with UUID v4 filenames, and create `LeadAttachment` entities. Verified in `ZohoAttachmentMigrationTest` and E2E Scenario 3. |
| 3 | Reconciliation report confirms 100% match on record counts, unique emails, and Active/Inactive statuses against Zoho sources. | ✓ VERIFIED | `ZohoReconciliationService`, `ReconciliationReportDTO`, and `ReconciliationReportGenerator` calculate discrepancies and output CSV/HTML reports with 100% match rate. Verified in `ZohoReconciliationReportTest` and E2E Scenario 4. |
| 4 | Cutover execution safely freezes Zoho Forms and redirects public applicants to the new membership form with tested rollback. | ✓ VERIFIED | `CUTOVER-RUNBOOK.md` establishes the 7-step cutover protocol, maintenance banners, DNS switch, 5 objective rollback triggers (TR-01..TR-05), and 30-minute rollback procedure. Verified in `ZohoDryRunAndCutoverTest`. |
| 5 | CLI command `zoho:import` parses raw Zoho Contacts and Leads CSV/JSON dumps in stream-chunked batches under 64MB memory envelope per MIGR-01. | ✓ VERIFIED | `ZohoImportCommand` implements memory-bounded stream iteration and garbage collection cycle triggers (`gc_collect_cycles()`), maintaining memory well below 64MB under multi-chunk load in `ZohoDataIngestionTest`. |
| 6 | Staging database tables `zoho_import_batches` and `zoho_staging_records` isolate raw payloads and capture validation status prior to target model hydration. | ✓ VERIFIED | Migration `2026_11_05_000002_create_zoho_staging_tables.php` creates isolated staging tables with JSON payloads, validation status enums, foreign keys, and indexes. Models `ZohoImportBatch` and `ZohoStagingRecord` implement `TenantEntityInterface` and `BelongsToTenant`. |
| 7 | Field mapping engine maps legacy attributes, dates, genders, bKash TxIDs, and dynamic term codes while preserving original Zoho record IDs in leads and members tables per MIGR-02. | ✓ VERIFIED | `ZohoFieldMappingService` handles Bangladeshi phone standardization (`+8801XXXXXXXXX`), division/district normalization, membership term resolution, bKash TxID cleaning, and date parsing. Verified across all test cases. |
| 8 | All migration transitions write append-only immutable audit events capturing batch metadata per DEC-11. | ✓ VERIFIED | Ingestion, attachment migration, reconciliation exports, and portal account staging all emit immutable `AuditLog` records with event constants (`zoho_batch_imported`, `zoho_attachment_migrated`, `zoho_reconciliation_exported`, `portal_account_staged_inactive`, `hub_delta_exported`). Verified in E2E Scenario 7. |
| 9 | Legacy Zoho attachment files (NID scans, passports, studio photos, parental consents) are matched to imported Leads and Members via manifest and `zoho_id` per MIGR-03. | ✓ VERIFIED | `ZohoImportAttachmentsCommand` parses CSV manifest mapping Zoho record IDs to files, resolving Member first and Lead second. Verified in `ZohoAttachmentMigrationTest`. |
| 10 | All legacy filenames are masked with cryptographic UUID v4 filenames outside the web root on private disk storage per ARCH-06 and DEC-09. | ✓ VERIFIED | `DocumentStorageService` and `StoragePathResolver` generate private disk storage paths with UUID v4 filenames. Stored files are verified to reside outside the web root in `ZohoAttachmentMigrationTest`. |
| 11 | Original filenames and SHA-256 hashes are recorded in metadata; files are encrypted at rest with AES-256. | ✓ VERIFIED | `LeadAttachment` records `original_filename`, `file_hash` (SHA-256), and `storage_path`. Files stored with `encryptAtRest: true` are encrypted via Laravel `Crypt` payload. Verified in E2E Scenario 3. |
| 12 | Strict path traversal defense guarantees that relative path notation (`..`), null bytes, and malicious extensions (`.php`, `.exe`, `.sh`) are rejected. | ✓ VERIFIED | `ZohoAttachmentMigrationService::sanitizeRelativePath()` traps null bytes, URL-encoded `%00`, directory traversal tokens (`..`), leading slashes, and Windows drive letters (`C:\`), verifying realpath containment. `validateFileSecurity()` traps prohibited executable extensions and checks magic bytes. |
| 13 | Youth protection flag (`youth_protection = true`) is automatically assigned to applicants under 18 or parental consent documents per DEC-09. | ✓ VERIFIED | `ZohoAttachmentMigrationService` calculates applicant age from date of birth, automatically applying `youth_protection = true` when age < 18 or document type is `parental_consent`. Verified in `ZohoAttachmentMigrationTest`. |
| 14 | Reconciliation service validates 100% record match across counts, unique emails, active/inactive statuses, and dates against Zoho source data per MIGR-04. | ✓ VERIFIED | `ZohoReconciliationService::evaluateRecords()` compares source staging counts, imported entities, email sets, dates, and attachments, returning `ReconciliationReportDTO` with `isReconciled() === true` and `matchPercentage() === 100.0`. |
| 15 | Discrepancy engine flags any mismatch in record counts, duplicate emails, status divergence, or missing attachments with actionable severity ratings. | ✓ VERIFIED | Discrepancy engine flags record omissions (`critical`), status divergence (`warning`), email duplicates (`warning`), date mismatches (`warning`), and missing private disk attachments (`critical`) with detailed metadata. |
| 16 | Reconciliation reports can be generated and exported in both CSV and downloadable HTML formats per MIGR-04. | ✓ VERIFIED | `ReconciliationReportGenerator::toCsv()` produces RFC 4180 CSV with formula injection defense (`'` prepended to `=`, `+`, `-`, `@`). `toHtml()` outputs self-contained, XSS-escaped HTML report. Verified in `ZohoReconciliationReportTest`. |
| 17 | Workspace Managers can inspect reconciliation metrics strictly scoped to their workspace via `/crm/{workspace}/migration/reconciliation` per DEC-01. | ✓ VERIFIED | `ReconciliationDashboard` Livewire component uses `ValidatesWorkspaceOnHydration`, asserts `migration.reconcile_data` permission, restricts cross-workspace managers with HTTP 403, and allows managers to view filtered discrepancies and trigger CSV/HTML downloads. |
| 18 | Dry-run validation engine executes full staging and transformation simulation inside an atomic transaction that always rolls back, leaving zero database mutations per MIGR-05. | ✓ VERIFIED | `ZohoDryRunEngine` records table row counts before execution across 9 tables, simulates ingestion and reconciliation inside `DB::beginTransaction()`, guarantees `DB::rollBack()` in `finally`, and verifies count invariants with zero mutations. Verified in `ZohoDryRunAndCutoverTest` and E2E Scenario 1. |
| 19 | Migrated members have member portal accounts staged in an inactive state (`guard = 'member'`, `is_active = false`, `must_reset_password = true`, `password = null`) requiring official claim per DEC-06. | ✓ VERIFIED | `PortalAccountProvisioningService` stages passwordless inactive user records linked to imported members, preventing pre-activation login leaks while preparing accounts for self-service claim. Verified in `ZohoDryRunAndCutoverTest`. |
| 20 | Inactive member accounts cannot authenticate via member portal until claimed and password set per DEC-06. | ✓ VERIFIED | `PortalAccountProvisioningService::assertAccountCannotLogin()` and E2E Scenario 5 verify that authentication against `Auth::guard('member')` fails closed on inactive accounts with empty or candidate passwords. |
| 21 | Emergency rollback tool `zoho:export-hub-delta` extracts new applications submitted during cutover window into Zoho-compliant CSV format satisfying TR-01 through TR-05 rollback triggers. | ✓ VERIFIED | `HubDeltaExportService` and `ZohoExportHubDeltaCommand` query leads submitted after cutoff timestamp, export Zoho CRM-compatible CSV with formula injection defense, lock file permissions, and log audit trails. Verified in `ZohoDryRunAndCutoverTest` and E2E Scenario 6. |
| 22 | `CUTOVER-RUNBOOK.md` codifies the 7-step cutover protocol, maintenance banners, DNS switch, rollback criteria, and team sign-offs per GAP-P1-06. | ✓ VERIFIED | `.planning/phases/11-zoho-migration-and-reconciliation/CUTOVER-RUNBOOK.md` contains the full 7-step operational cutover protocol, Mermaid diagrams, quantifiable rollback triggers TR-01 through TR-05, the 30-minute reversal sequence, and sign-off table. |
| 23 | Architecture tests enforce that `withoutGlobalScope` is forbidden across `App\Domain\Migration` and `App\Livewire\Migration` per DEC-01. | ✓ VERIFIED | Pest architecture test in `Phase11ArchitectureTest.php` asserts zero usage of `withoutGlobalScope` or `withoutGlobalScopes`. Passed. |
| 24 | Architecture tests assert zero dynamic code evaluation (`eval`, `create_function`) per DEC-02. | ✓ VERIFIED | Pest architecture test asserts zero usage of `eval` or `create_function` across `App\Domain\Migration` and `App\Livewire\Migration`. Passed. |
| 25 | Architecture tests guarantee global `route()` helper is never used in `App\Domain\Migration` classes, adhering to `\Illuminate\Support\Facades\URL::route()` discipline. | ✓ VERIFIED | Pest architecture test and recursive AST regex test assert zero calls to global `route()` helper in `App\Domain\Migration`. Passed. |
| 26 | `ModuleRegistry` cleanly binds migration actions and routes. | ✓ VERIFIED | `ModuleRegistry` statically compiles `migration` module, action definitions (`migration.import_zoho`, `migration.export_zoho`, `migration.reconcile_data`), locks non-delegable actions in `STRICTLY_NON_DELEGABLE`, and registers default route `crm.migration.reconciliation`. Verified in `Phase11ArchitectureTest`. |
| 27 | Staging and import models implement `TenantEntityInterface` and use `BelongsToTenant`. | ✓ VERIFIED | `ZohoImportBatch` and `ZohoStagingRecord` implement `TenantEntityInterface`, use `BelongsToTenant` trait, and define `getWorkspaceId()`. Verified in `Phase11ArchitectureTest`. |
| 28 | Reconciliation dashboard implements Livewire workspace hydration guard per DEC-01. | ✓ VERIFIED | `ReconciliationDashboard` uses `ValidatesWorkspaceOnHydration`, validating workspace context on every component hydration. Verified in `Phase11ArchitectureTest`. |
| 29 | Complete Phase 11 end-to-end holistic simulation executes with 100% pass rate. | ✓ VERIFIED | `EndToEndZohoMigrationAndReconciliationTest` executes full multi-stage simulation covering dry-run, live ingestion, attachment encryption, reconciliation match, inactive portal staging, rollback delta export, and audit logging with 0 failures. |

**Score:** 29/29 truths verified (0 present, behavior-unverified)

---

## Required Artifacts

| Artifact | Expected | Status | Details |
|---|---|---|---|
| `database/migrations/2026_11_05_000002_create_zoho_staging_tables.php` | Staging schema for `zoho_import_batches` and `zoho_staging_records` | ✓ VERIFIED | Created with tenant scoping, JSON payloads, enums, foreign keys, and indexes. (Sequenced as `000002` to follow Phase 10 `000001_create_platform_settings_table.php`). |
| `app/Domain/Migration/Models/ZohoImportBatch.php` | Eloquent model for batch tracking | ✓ VERIFIED | Implements `TenantEntityInterface`, uses `BelongsToTenant`, typed casts, and `records()` relationship. |
| `app/Domain/Migration/Models/ZohoStagingRecord.php` | Eloquent model for staged raw and mapped payloads | ✓ VERIFIED | Implements `TenantEntityInterface`, uses `BelongsToTenant`, typed casts, and `batch()` relationship. |
| `app/Domain/Migration/Services/ZohoFieldMappingService.php` | Legacy Zoho field transformation engine | ✓ VERIFIED | Comprehensive normalizer for names, phones (`+8801XXXXXXXXX`), divisions, districts, terms, bKash TxIDs, and dates while preserving `zoho_id`. |
| `app/Domain/Migration/Services/ZohoDataIngestionService.php` | Streamed chunked CSV ingestion orchestrator | ✓ VERIFIED | Stream reader with memory bounds (<64MB envelope), atomic chunk transactions, staging isolation, duplicate prevention, and audit logging. |
| `app/Domain/Migration/Commands/ZohoImportCommand.php` | Artisan CLI tool `zoho:import` | ✓ VERIFIED | Stream-chunked CLI import with progress bar, workspace validation, memory limits, and dry-run flag. |
| `app/Domain/Migration/Services/ZohoAttachmentMigrationService.php` | Secure attachment matching and encryption engine | ✓ VERIFIED | Path traversal traps, null-byte traps, whitelist MIME and magic bytes validation, EXIF stripping, AES-256 encryption at rest, and youth protection tagging. |
| `app/Domain/Migration/Commands/ZohoImportAttachmentsCommand.php` | Artisan CLI tool `zoho:import-attachments` | ✓ VERIFIED | Batch attachment importer using CSV manifest with realpath validation and progress tracking. |
| `app/Domain/Migration/Exceptions/SecurityException.php` | Custom exception for path traversal and security violations | ✓ VERIFIED | Explicit security exception class used across attachment sanitization routines. |
| `app/Domain/Migration/DTOs/ReconciliationReportDTO.php` | Structured reconciliation metrics DTO | ✓ VERIFIED | Encapsulates source vs imported counts, email stats, date mismatches, missing attachments, and discrepancy items with `isReconciled()` and `matchPercentage()`. |
| `app/Domain/Migration/Services/ZohoReconciliationService.php` | Discrepancy detection engine | ✓ VERIFIED | Automated validator comparing staging records against persisted entities with granular discrepancy categorisation and audit logging. |
| `app/Domain/Migration/Services/ReconciliationReportGenerator.php` | Multi-format report exporter | ✓ VERIFIED | Formatter for CSV (with formula injection defense) and HTML (with XSS sanitization). |
| `app/Domain/Migration/Commands/ZohoReconcileCommand.php` | Artisan CLI tool `zoho:reconcile` | ✓ VERIFIED | CLI reconciliation command supporting console tables, CSV exports, and HTML report files. |
| `app/Livewire/Migration/ReconciliationDashboard.php` | Workspace-scoped Livewire reconciliation console | ✓ VERIFIED | Reactive dashboard with `ValidatesWorkspaceOnHydration`, `migration.reconcile_data` permission check, discrepancy filtering, pagination, and CSV/HTML streaming. |
| `resources/views/livewire/migration/reconciliation-dashboard.blade.php` | Tailwind CSS dashboard blade template | ✓ VERIFIED | Admin layout component rendering reconciliation cards, discrepancy tables, filter dropdowns, and download triggers. |
| `routes/web.php` | Route registration | ✓ VERIFIED | Registers `/crm/{workspace}/migration/reconciliation` with `can:migration.reconcile_data` middleware. |
| `app/Domain/Migration/Services/ZohoDryRunEngine.php` | Transactional dry-run simulation engine | ✓ VERIFIED | Runs end-to-end ingestion and reconciliation inside atomic transaction with guaranteed rollback and pre/post table count invariance verification. |
| `app/Domain/Migration/Services/PortalAccountProvisioningService.php` | Inactive portal credential staging service | ✓ VERIFIED | Stages passwordless inactive user accounts (`guard='member'`, `is_active=false`, `password=null`) and verifies fail-closed authentication. |
| `app/Domain/Migration/Services/HubDeltaExportService.php` | Emergency rollback delta export engine | ✓ VERIFIED | Extracts new lead applications submitted after cutover into Zoho CRM-compliant CSV format with formula injection defenses and audit trails. |
| `app/Domain/Migration/Commands/ZohoDryRunCommand.php` | Artisan CLI tool `zoho:dry-run` | ✓ VERIFIED | CLI interface for transactional simulation with rollback and zero mutations. |
| `app/Domain/Migration/Commands/ZohoProvisionPortalAccountsCommand.php` | Artisan CLI tool `zoho:provision-portal-accounts` | ✓ VERIFIED | CLI interface for staging inactive portal user credentials for migrated members. |
| `app/Domain/Migration/Commands/ZohoExportHubDeltaCommand.php` | Artisan CLI tool `zoho:export-hub-delta` | ✓ VERIFIED | CLI interface for exporting post-cutoff applications to sanitized CSV for cutover reversal. |
| `.planning/phases/11-zoho-migration-and-reconciliation/CUTOVER-RUNBOOK.md` | Production cutover and rollback runbook | ✓ VERIFIED | Comprehensive 7-step cutover protocol, 5 rollback triggers (TR-01..TR-05), 30-minute reversal sequence, and sign-off authority table. |
| `app/Domain/Platform/Registry/ModuleRegistry.php` | Platform module and action catalog | ✓ VERIFIED | Declares `migration` module, `crm.migration.reconciliation` default route, and non-delegable actions `migration.import_zoho` and `migration.export_zoho`. |
| `tests/Feature/ZohoDataIngestionTest.php` | Feature test suite for ingestion and field mapping | ✓ VERIFIED | 11 tests verifying staging schema, mapping fidelity, duplicate handling, memory bounds, and CLI commands. |
| `tests/Feature/ZohoAttachmentMigrationTest.php` | Feature test suite for attachment security | ✓ VERIFIED | 15 tests verifying secure storage, path traversal defenses, null-byte traps, magic bytes checks, youth protection, and CLI commands. |
| `tests/Feature/ZohoReconciliationReportTest.php` | Feature test suite for reconciliation engine | ✓ VERIFIED | 13 tests verifying 100% match detection, discrepancy logging, formula injection defense, HTML generation, CLI output, and Livewire dashboard access. |
| `tests/Feature/ZohoDryRunAndCutoverTest.php` | Feature test suite for dry-run and cutover | ✓ VERIFIED | 9 tests verifying zero-mutation rollback, inactive portal provisioning, delta CSV export, formula sanitization, and runbook compliance. |
| `tests/Feature/EndToEndZohoMigrationAndReconciliationTest.php` | Comprehensive holistic E2E test suite | ✓ VERIFIED | 8 comprehensive scenarios verifying full multi-stage migration pipeline and audit trail integrity. |
| `tests/Arch/Phase11ArchitectureTest.php` | Pest architecture and tenancy guard suite | ✓ VERIFIED | 9 architecture tests (48 assertions) enforcing tenancy invariants, immutability, strict types, route helper avoidance, and permission gates. |

---

## Key Link Verification

| From | To | Via | Status | Details |
|---|---|---|---|---|
| `ZohoImportCommand` | `ZohoDataIngestionService` | `ingestFile()` method call | ✓ WIRED | Command parses arguments/options, validates file and workspace, and invokes stream ingestion with progress bar callbacks. |
| `ZohoDataIngestionService` | `ZohoFieldMappingService` | `mapRecord()` method call | ✓ WIRED | Ingestion service passes raw CSV rows to mapping service for field normalization and validation status resolution. |
| `ZohoDataIngestionService` | `Lead` / `Member` | Eloquent creation / hydration | ✓ WIRED | Mapped payloads persist to domain models with original `zoho_id` indexed and staging status updated to `imported`. |
| `ZohoAttachmentMigrationService` | `DocumentStorageService` | `store()` method call | ✓ WIRED | Attachment service passes sanitized file content to `DocumentStorageService` for AES-256 encryption on private disk. |
| `ZohoImportAttachmentsCommand` | `ZohoAttachmentMigrationService` | `migrateAttachment()` | ✓ WIRED | Command reads manifest CSV and dispatches individual files to attachment service for validation and storage. |
| `ZohoReconciliationService` | `ZohoStagingRecord` / `Member` / `Lead` | Eloquent cross-queries | ✓ WIRED | Service compares staging records against core domain entities to build discrepancy collections. |
| `ReconciliationReportGenerator` | `ReconciliationReportDTO` | `toCsv()` / `toHtml()` | ✓ WIRED | Formatter consumes DTO properties and discrepancies array, generating formula-safe CSV and XSS-safe HTML. |
| `ReconciliationDashboard` | `ZohoReconciliationService` | `reconcileWorkspace()` / `reconcileBatch()` | ✓ WIRED | Livewire component calls service on mount/render and delegates CSV/HTML downloads. |
| `routes/web.php` | `ReconciliationDashboard` | Route declaration | ✓ WIRED | Route `/crm/{workspace}/migration/reconciliation` binds to `ReconciliationDashboard::class` with `can:migration.reconcile_data`. |
| `ZohoDryRunEngine` | `DB::transaction` / `DB::rollBack` | Atomic transaction block | ✓ WIRED | Executes ingestion and reconciliation inside transaction and guarantees rollback in `finally` block. |
| `PortalAccountProvisioningService` | `User` / `Member` | Passwordless user creation | ✓ WIRED | Creates/updates user record with `guard='member'`, `is_active=false`, `password=null`, and associates `user_id` on Member. |
| `HubDeltaExportService` | `Lead` | Eloquent query with cutoff filter | ✓ WIRED | Queries leads submitted after cutoff date and streams sanitized CSV with formula escaping. |

---

## Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|---|---|---|---|---|
| `ZohoDataIngestionService` | `$batch` / `$stagingRecord` | Real CSV stream reader (`streamCsvRows`) | Yes — reads line-by-line from source CSV file and writes real staging records | ✓ FLOWING |
| `ZohoFieldMappingService` | `$mapped` | Raw CSV header-value array | Yes — extracts and normalizes real phone numbers, names, dates, terms, and bKash TxIDs | ✓ FLOWING |
| `ZohoAttachmentMigrationService` | `$storedMeta` | `DocumentStorageService::store` on private disk | Yes — generates real AES-256 encrypted payload and SHA-256 hash on private filesystem | ✓ FLOWING |
| `ZohoReconciliationService` | `$report` (DTO) | Live queries against `zoho_staging_records`, `leads`, `members`, `lead_attachments` | Yes — aggregates real database row counts, emails, dates, and attachment counts | ✓ FLOWING |
| `ReconciliationDashboard` | `$report` | `ZohoReconciliationService::reconcileWorkspace` | Yes — passes live calculated reconciliation metrics to view template | ✓ FLOWING |
| `ReconciliationDashboard` | `$discrepancies` | Paginator over `$report->discrepancies` | Yes — displays real filtered discrepancy records with severity badges | ✓ FLOWING |
| `HubDeltaExportService` | CSV stream | `Lead::where('submitted_at', '>=', ...)` | Yes — outputs real applicant delta rows submitted during cutover window | ✓ FLOWING |

---

## Behavioral Spot-Checks

| Behavior | Command | Result | Status |
|---|---|---|---|
| Phase 11 E2E Integration & Architecture Test Suite | `php artisan test tests/Feature/EndToEndZohoMigrationAndReconciliationTest.php tests/Arch/Phase11ArchitectureTest.php` | `17 passed (592 assertions) in 35.99s` | ✓ PASS |
| Phase 11 Ingestion, Attachment, Reconciliation & Cutover Suite | `php artisan test tests/Feature/ZohoDataIngestionTest.php tests/Feature/ZohoAttachmentMigrationTest.php tests/Feature/ZohoReconciliationReportTest.php tests/Feature/ZohoDryRunAndCutoverTest.php` | `48 passed (285 assertions) in 7.17s` | ✓ PASS |
| Complete Phase 11 Combined Test Suite | All 6 test files | `65 passed (877 assertions, 0 failures)` | ✓ PASS |
| Artisan `zoho:import` CLI Interface | `php artisan zoho:import --help` | Displays valid arguments (`file`), options (`--workspace`, `--type`, `--chunk`, `--dry-run`), and description. | ✓ PASS |
| Artisan `zoho:import-attachments` CLI Interface | `php artisan zoho:import-attachments --help` | Displays valid arguments (`directory`), options (`--manifest`, `--workspace`, `--dry-run`), and description. | ✓ PASS |
| Artisan `zoho:reconcile` CLI Interface | `php artisan zoho:reconcile --help` | Displays valid options (`--batch`, `--workspace`, `--format`, `--output`), and description. | ✓ PASS |
| Artisan `zoho:dry-run` CLI Interface | `php artisan zoho:dry-run --help` | Displays valid arguments (`file`), options (`--workspace`, `--type`, `--manifest`, `--attachments-dir`), and description. | ✓ PASS |
| Artisan `zoho:provision-portal-accounts` CLI Interface | `php artisan zoho:provision-portal-accounts --help` | Displays valid options (`--workspace`, `--batch`), and description. | ✓ PASS |
| Artisan `zoho:export-hub-delta` CLI Interface | `php artisan zoho:export-hub-delta --help` | Displays valid options (`--since`, `--workspace`, `--output`), and description. | ✓ PASS |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|---|---|---|---|---|
| **MIGR-01** | 11-01, 11-05 | Implement repeatable data import pipeline for Zoho Leads, Contacts/Members, form entries, documents, and photos. | ✓ SATISFIED | `ZohoDataIngestionService` provides stream-chunked CSV parsing under 64MB memory envelope. Verified by `ZohoDataIngestionTest` and E2E Scenario 2. |
| **MIGR-02** | 11-01, 11-05 | Map legacy fields, terms, and statuses while preserving Zoho record IDs for audit traceability. | ✓ SATISFIED | `ZohoFieldMappingService` transforms legacy structures into Hub entities while indexing original `zoho_id` across `leads`, `members`, and staging tables. Verified in `ZohoDataIngestionTest`. |
| **MIGR-03** | 11-02, 11-05 | Safely associate legacy documents and photos with imported member profiles under secure naming conventions. | ✓ SATISFIED | `ZohoAttachmentMigrationService` matches attachments via manifest, renames with UUID v4 filenames on private disk, encrypts at rest (AES-256), defends against path traversal and null bytes, and flags youth protection for minors. Verified in `ZohoAttachmentMigrationTest` and E2E Scenario 3. |
| **MIGR-04** | 11-03, 11-05 | Generate reconciliation reports verifying record counts, active/inactive statuses, unique emails, and dates against Zoho source data. | ✓ SATISFIED | `ZohoReconciliationService`, `ReconciliationReportDTO`, `ReconciliationReportGenerator`, `ZohoReconcileCommand`, and `ReconciliationDashboard` verify 100% match with CSV/HTML exports and formula injection defenses. Verified in `ZohoReconciliationReportTest` and E2E Scenario 4. |
| **MIGR-05** | 11-04, 11-05 | Execute dry-run verification, import portal accounts in inactive state, and establish cutover/rollback procedures. | ✓ SATISFIED | `ZohoDryRunEngine` with transactional auto-rollback and zero database mutations, `PortalAccountProvisioningService` staging inactive passwordless credentials, `HubDeltaExportService` extracting post-cutoff leads with formula escaping, and `CUTOVER-RUNBOOK.md` codifying 7-step cutover and TR-01..TR-05 triggers. Verified in `ZohoDryRunAndCutoverTest` and E2E Scenarios 1, 5, 6. |

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|---|---|---|---|---|
| None | N/A | None | N/A | Zero debt markers (`TBD`, `FIXME`, `XXX`), zero placeholder comments, zero stub implementations detected across all Phase 11 domain services, commands, models, Livewire components, and test files. |

---

## Human Verification Required

None. All 29 must-haves, state transitions, security guards, and behavioral invariants are programmatically verified via automated Pest/PHPUnit test suites (65 tests, 877 assertions) executing across CLI commands, Livewire components, database transactions, filesystem encryption routines, and architecture boundary assertions.

---

## Gaps Summary

None. All 5 Phase 11 requirements (MIGR-01 through MIGR-05) are fully satisfied and substantiated by production implementation and comprehensive automated tests.

---

_Verified: 2026-11-05T07:50:00Z_  
_Verifier: the agent (gsd-verifier)_

