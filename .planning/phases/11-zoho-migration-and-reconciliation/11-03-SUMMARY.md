---
phase: 11-zoho-migration-and-reconciliation
plan: "03"
subsystem: migration
tags:
  - zoho-reconciliation
  - discrepancy-detection
  - data-fidelity
  - formula-injection-defense
  - xss-defense
  - livewire-dashboard
  - csv-export
  - html-export
  - cli-command
  - tenancy-isolation
  - immutable-audit
dependency_graph:
  requires:
    - 01-discovery-decisions-and-acceptance-criteria
    - 02-architecture-data-model-and-security-design
    - "11-01"
    - "11-02"
  provides:
    - zoho-reconciliation-service
    - reconciliation-report-generator
    - artisan-zoho-reconcile-command
    - livewire-reconciliation-dashboard
  affects:
    - app/Providers/AppServiceProvider.php
    - routes/web.php
    - app/Domain/Audit/Models/AuditLog.php
    - database/seeders/RolesAndPermissionsSeeder.php
tech_stack:
  added: []
  patterns:
    - automated-cross-entity-reconciliation
    - formula-injection-sanitization
    - xss-escaping
    - scoped-tenancy-verification
    - reactive-livewire-filtering
    - streamed-export-downloads
    - append-only-audit-logging
key_files:
  created:
    - app/Domain/Migration/DTOs/ReconciliationReportDTO.php
    - app/Domain/Migration/Services/ZohoReconciliationService.php
    - app/Domain/Migration/Services/ReconciliationReportGenerator.php
    - app/Domain/Migration/Commands/ZohoReconcileCommand.php
    - app/Livewire/Migration/ReconciliationDashboard.php
    - resources/views/livewire/migration/reconciliation-dashboard.blade.php
    - tests/Feature/ZohoReconciliationReportTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Providers/AppServiceProvider.php
    - database/seeders/RolesAndPermissionsSeeder.php
    - routes/web.php
decisions:
  - "Strict workspace scoping on all reconciliation queries asserting tenancy per DEC-01 (D-01)."
  - "Defended CSV exports against formula injection (T-11-09) by prepending single quotes to values starting with '=', '+', '-', '@'."
  - "Escaped HTML entities across all discrepancy report renderings to prevent cross-site scripting (XSS)."
  - "Protected Livewire ReconciliationDashboard with workspace.access middleware, can:migration.reconcile_data role permission, and component-level tenancy assertions."
  - "Recorded immutable audit logs for all reconciliation runs and report exports in audit_logs per DEC-11 (D-11)."
metrics:
  duration: 20 min
  completed_date: "2026-11-05"
status: complete
actuals:
  tokens: 28000
  tasks: 2
  commits: 2
  plan_head_before: "655d5fadbf38455bbe58603652fcfbf86f7aea5c"
  plan_head_after: "eb4f0ad90176419595f052511bf07f140a6beb7c"
---

# Phase 11 Plan 03: Automated Reconciliation Engine & Livewire Dashboard Summary

**Automated discrepancy detection engine cross-checking Zoho source data with database entities, CSV/HTML report generation with formula injection and XSS defenses, Artisan CLI reconciliation command, and reactive workspace-scoped Livewire reconciliation dashboard.**

---

## Executive Summary

Phase 11 Plan 03 completed the legacy Zoho migration subsystem by implementing automated reconciliation and reporting tools per **MIGR-04**, **DEC-01 (D-01)**, and **DEC-11 (D-11)**.

The reconciliation engine verifies 100% data fidelity between legacy Zoho staging records and production CRM entities (`members`, `leads`, `lead_attachments`). It audits:
1. **Record counts and presence**: Confirms each staged record was successfully ingested or flags missing targets as critical discrepancies.
2. **Status alignment**: Cross-validates source statuses against normalized entity states.
3. **Email integrity & uniqueness**: Validates email format and detects multiple Zoho IDs mapped to identical email addresses.
4. **Key date fidelity**: Evaluates `start_date`, `expiry_date`, and `date_of_birth` down to the day.
5. **Attachment completeness**: Validates that all expected document and photo attachments exist on the private filesystem disk.

Results are compiled into `ReconciliationReportDTO` with exact match percentages and actionable severity ratings (`critical`, `warning`, `info`). Reports can be exported via `ReconciliationReportGenerator` into RFC 4180 CSV (with CSV formula injection defense per **T-11-09**) and standalone XSS-safe HTML.

Operators can execute reconciliations through the CLI command `zoho:reconcile` or inspect live metrics in the browser via the workspace-scoped Livewire `ReconciliationDashboard` at `/crm/{workspace}/migration/reconciliation`.

---

## Key Deliverables & Implementation Details

### 1. Discrepancy Detection & Reconciliation Engine
- **`App\Domain\Migration\DTOs\ReconciliationReportDTO`**:
  - Encapsulates reconciliation metrics: source vs imported counts, match percentage, status distributions, unique/duplicate emails, date mismatches, and attachment completeness counts.
  - Exposes `isReconciled(): bool` (true only when discrepancies = 0) and `matchPercentage(): float`.
- **`App\Domain\Migration\Services\ZohoReconciliationService`**:
  - `reconcileBatch(ZohoImportBatch $batch)`: Scoped reconciliation for a specific import batch.
  - `reconcileWorkspace(string $workspaceId)`: Holistic reconciliation across all staging records in a workspace.
  - Cross-validates presence, statuses, emails, dates (`start_date`, `expiry_date`, `date_of_birth`), and attachments on `Storage::disk('private')`.
  - Emits immutable `AuditLog` records with event type `zoho_reconciliation_run` per DEC-11.

### 2. Reconciliation Report Generator (CSV/HTML)
- **`App\Domain\Migration\Services\ReconciliationReportGenerator`**:
  - `toCsv(ReconciliationReportDTO $report)`: Streams RFC 4180 CSV with standard discrepancy columns (`zoho_id`, `entity`, `field`, `expected_value`, `actual_value`, `severity`, `message`).
  - **Formula Injection Defense (T-11-09)**: Prepends a single quote (`'`) to any cell starting with `=`, `+`, `-`, or `@`.
  - `toHtml(ReconciliationReportDTO $report)`: Generates self-contained HTML reports with status badges, KPI cards, and discrepancy ledger. All data is sanitized using `htmlspecialchars` to prevent XSS.

### 3. Artisan CLI Reconciliation Command
- **`App\Domain\Migration\Commands\ZohoReconcileCommand`**:
  - Registered as `zoho:reconcile {--batch=} {--workspace=ws-emk-dhaka} {--format=console} {--output=}`.
  - Supports `console` table output, `csv` export, and `html` report generation directly to file paths.

### 4. Reactive Livewire Reconciliation Dashboard
- **`App\Livewire\Migration\ReconciliationDashboard`**:
  - Route: `/crm/{workspace}/migration/reconciliation` named `crm.migration.reconciliation`.
  - Guarded by `workspace.access`, `auth:web`, `can:migration.reconcile_data`, and component-level tenancy verification (`ValidatesWorkspaceOnHydration`). Cross-workspace access is rejected with HTTP 403.
  - Provides real-time batch switching, severity filtering (`all`, `critical`, `warning`, `info`), and Zoho ID search.
  - Download actions for CSV and HTML streaming with append-only audit event logging (`zoho_reconciliation_exported`).
- **`resources/views/livewire/migration/reconciliation-dashboard.blade.php`**:
  - Tailwind-styled responsive UI featuring dynamic fidelity banners, KPI metrics grid, and paginated discrepancy ledger.

---

## Verification Results

### Test Execution
```bash
php artisan test tests/Feature/ZohoReconciliationReportTest.php
```
```
PASS  Tests\Feature\ZohoReconciliationReportTest
✓ reconciliation on 100 percent matching dataset returns reconciled (0.61s)
✓ detection of count mismatch when records are omitted (0.10s)
✓ detection of email duplication and formatting divergence (0.10s)
✓ detection of date discrepancies (0.10s)
✓ detection of missing attachments on private disk (0.10s)
✓ workspace level reconciliation evaluates all workspace records (0.10s)
✓ formula injection defense prepends quote to formula triggers (0.09s)
✓ html report generator produces valid and xss escaped html (0.08s)
✓ artisan zoho reconcile command outputs console table (0.10s)
✓ artisan zoho reconcile command generates csv and html files (0.10s)
✓ authenticated workspace manager can access reconciliation dashboard (0.18s)
✓ cross workspace manager receives 403 forbidden (0.10s)
✓ dashboard export streams csv and html with audit log (0.13s)

Tests: 13 passed (77 assertions)
Duration: 2.10s
```

### Regression & Architecture Verification
```bash
php artisan test tests/Feature/ZohoAttachmentMigrationTest.php tests/Feature/ZohoDataIngestionTest.php tests/Arch
```
```
PASS  Tests\Feature\ZohoAttachmentMigrationTest (15 tests, 51 assertions)
PASS  Tests\Feature\ZohoDataIngestionTest (11 tests, 84 assertions)
PASS  Tests\Arch\ModuleRegistryArchTest (3 tests, 4 assertions)
PASS  Tests\Arch\Phase10ArchitectureTest (6 tests, 6 assertions)
PASS  Tests\Arch\TenancyArchTest (2 tests, 60 assertions)

All 50 tests passed cleanly with 282 assertions.
```

---

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Column name in MembershipTerm**
- **Found during:** Task 1 test run
- **Issue:** Test fixture attempted to use `fee_amount` on `MembershipTerm`, but schema defines `fee_bdt`.
- **Fix:** Switched test creation to use `MembershipTerm::where('workspace_id', ...)->first() ?? MembershipTerm::create(['fee_bdt' => 1000.00, ...])`.
- **Files modified:** `tests/Feature/ZohoReconciliationReportTest.php`

**2. [Rule 2 - Critical Functionality] Add migration.% permission to workspace_manager in seeder**
- **Found during:** Task 2 route testing
- **Issue:** `workspace_manager` role did not automatically inherit newly added `migration.*` permissions in `RolesAndPermissionsSeeder`.
- **Fix:** Added `->orWhere('name', 'like', 'migration.%')` to `RolesAndPermissionsSeeder.php`.
- **Files modified:** `database/seeders/RolesAndPermissionsSeeder.php`

**3. [Rule 1 - Bug] Architecture rule compliance for global collect() helper**
- **Found during:** Arch test verification
- **Issue:** Architecture test flags global `collect()` helper inside `App\Domain` namespace.
- **Fix:** Replaced `collect()` with `new Collection()` in `ZohoReconciliationService.php`.
- **Files modified:** `app/Domain/Migration/Services/ZohoReconciliationService.php`

---

## Self-Check: PASSED

- [x] All 7 created files exist on disk
- [x] Both commits exist in git history (`dbfa95f`, `eb4f0ad`)
- [x] Invariant 1: Strict workspace tenancy enforced across all reconciliation queries
- [x] Invariant 2: Formula injection and XSS defenses implemented and verified
- [x] Invariant 3: Dashboard route protected by `can:migration.reconcile_data` and 403 on cross-workspace access
- [x] Invariant 4: Immutable audit logs recorded on reconciliation run and report export
- [x] Invariant 5: No global `route()` helpers used in `App\Domain`

