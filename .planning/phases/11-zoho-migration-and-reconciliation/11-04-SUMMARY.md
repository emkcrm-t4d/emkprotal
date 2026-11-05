---
phase: 11-zoho-migration-and-reconciliation
plan: "04"
subsystem: migration
tags:
  - dry-run-engine
  - transactional-rollback
  - cutover-readiness
  - portal-account-provisioning
  - passwordless-staging
  - member-portal-security
  - delta-export-service
  - emergency-rollback-protocol
  - csv-formula-injection-defense
  - cutover-runbook
  - immutable-audit
dependency_graph:
  requires:
    - 01-discovery-decisions-and-acceptance-criteria
    - 02-architecture-data-model-and-security-design
    - "11-01"
    - "11-02"
    - "11-03"
  provides:
    - zoho-dry-run-engine
    - portal-account-provisioning-service
    - hub-delta-export-service
    - artisan-zoho-dry-run-command
    - artisan-zoho-provision-portal-accounts-command
    - artisan-zoho-export-hub-delta-command
    - codified-cutover-runbook
  affects:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Migration/Services/ZohoDataIngestionService.php
    - app/Models/User.php
    - app/Providers/AppServiceProvider.php
    - database/migrations/2026_11_03_000004_create_members_invoices_cards_tables.php
tech_stack:
  added: []
  patterns:
    - guaranteed-atomic-transaction-rollback
    - staged-inactive-credentials-pattern
    - formula-injection-sanitization
    - gated-cutover-protocol
    - append-only-audit-logging
    - restricted-file-permissions
key_files:
  created:
    - app/Domain/Migration/Services/ZohoDryRunEngine.php
    - app/Domain/Migration/Services/PortalAccountProvisioningService.php
    - app/Domain/Migration/Services/HubDeltaExportService.php
    - app/Domain/Migration/Commands/ZohoDryRunCommand.php
    - app/Domain/Migration/Commands/ZohoProvisionPortalAccountsCommand.php
    - app/Domain/Migration/Commands/ZohoExportHubDeltaCommand.php
    - .planning/phases/11-zoho-migration-and-reconciliation/CUTOVER-RUNBOOK.md
    - tests/Feature/ZohoDryRunAndCutoverTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Migration/Services/ZohoDataIngestionService.php
    - app/Models/User.php
    - app/Providers/AppServiceProvider.php
    - database/migrations/2026_11_03_000004_create_members_invoices_cards_tables.php
decisions:
  - "Guaranteed dry-run rollback in finally block ensuring 0 database writes per MIGR-05 and T-11-10."
  - "Staged legacy member portal accounts with guard='member', is_active=false, must_reset_password=true, and password=null per DEC-06 (D-06) and T-11-11."
  - "Enforced fails-closed authentication on member guard rejecting login for unactivated accounts."
  - "Sanitized cutover delta export CSV against formula injection by prepending single quote to =, +, -, @ per T-11-12."
  - "Codified 7-step cutover protocol, maintenance banners, DNS switch, 5 quantifiable rollback triggers (TR-01 through TR-05), and 30-minute reversal sequence in CUTOVER-RUNBOOK.md per GAP-P1-06."
metrics:
  duration: 25 min
  completed_date: "2026-11-05"
status: complete
actuals:
  tokens: 31000
  tasks: 2
  commits: 2
  plan_head_before: "433a0fd2531bed85b057f5d3df91a4e525897727"
  plan_head_after: "ea51d77d526f68c08f0aa9eed78dabedf0665ff4"
---

# Phase 11 Plan 04: Transactional Dry-Run Engine, Inactive Portal Account Provisioner, Delta Rollback Exporter, & Cutover Runbook Summary

**Zero-risk cutover readiness through atomic transactional dry-run simulation, secure inactive staging of legacy portal credentials, emergency delta export tooling with CSV injection defense, and a codified 7-step production cutover runbook.**

---

## Executive Summary

Phase 11 Plan 04 delivered the operational cutover and migration validation tools mandated by **MIGR-05**, **DEC-06 (D-06)**, **DEC-11 (D-11)**, and **GAP-P1-06**.

The migration subsystem now guarantees zero-risk deployment through:
1. **Transactional Dry-Run Validation (`ZohoDryRunEngine` & `zoho:dry-run`)**: Simulates complete end-to-end legacy CSV ingestion, attachment mapping, and discrepancy reconciliation inside an atomic transaction. A mandatory `DB::rollBack()` in a `finally` block ensures 100% rollback fidelity, leaving zero database mutations behind while returning full simulation statistics.
2. **Inactive Member Portal Account Staging (`PortalAccountProvisioningService` & `zoho:provision-portal-accounts`)**: Provisions member accounts in the `users` table for imported members with `guard = 'member'`, `is_active = false`, `must_reset_password = true`, and `password = null` per DEC-06. The authentication layer fails closed, ensuring imported members cannot authenticate until they complete the official "Claim Your Account" verification flow.
3. **Emergency Cutover Delta Exporter (`HubDeltaExportService` & `zoho:export-hub-delta`)**: Extracts all new applications submitted to the Laravel Hub during the cutover window into a Zoho-compliant CSV format with formula injection defenses (`'` prepended to `=`, `+`, `-`, `@`), enabling seamless re-import into legacy Zoho CRM if an emergency reversal occurs.
4. **Codified Production Cutover Runbook (`CUTOVER-RUNBOOK.md`)**: Formally documents the 7-step cutover protocol, maintenance banners, DNS switchover, Horizon queue activation, 5 objective rollback triggers (**TR-01** through **TR-05**), and the 30-minute emergency reversal protocol with stakeholder sign-off gates.

---

## Key Deliverables & Implementation Details

### 1. Transactional Dry-Run Validation Engine
- **Class:** `App\Domain\Migration\Services\ZohoDryRunEngine`
- **Command:** `php artisan zoho:dry-run {file} {--workspace=ws-emk-dhaka} {--type=contacts_members} {--manifest=} {--attachments-dir=}`
- **Guaranteed Isolation:** Opens `DB::beginTransaction()` and monitors row counts across core tables (`users`, `leads`, `lead_attachments`, `members`, `invoices`, `id_cards`, `zoho_import_batches`, `zoho_staging_records`, `audit_logs`).
- **Simulation Scope:** Ingests file via `ZohoDataIngestionService`, executes mock attachment association without permanent disk writes, runs discrepancy detection via `ZohoReconciliationService`, and verifies database state is completely unchanged post-rollback.
- **Reporting:** Renders console summary table with staged count, transformed count, validation errors, reconciliation match percentage, discrepancy counts, and rollback confirmation.

### 2. Inactive Portal Account Provisioning Service
- **Class:** `App\Domain\Migration\Services\PortalAccountProvisioningService`
- **Command:** `php artisan zoho:provision-portal-accounts {--workspace=ws-emk-dhaka} {--batch=}`
- **Security Invariant (T-11-11 / DEC-06):** Ingested members are staged with `guard = 'member'`, `is_active = false`, `must_reset_password = true`, and `password = null`.
- **Fails-Closed Authentication:** Confirmed through `assertAccountCannotLogin(User $user)` and live guard attempt assertions under `Auth::guard('member')`.
- **Audit Logging:** Emits immutable audit event `portal_account_staged_inactive` in `audit_logs` capturing user ID, member ID, email, and security attributes.

### 3. Cutover Delta Export Engine & Rollback Command
- **Class:** `App\Domain\Migration\Services\HubDeltaExportService`
- **Command:** `php artisan zoho:export-hub-delta {--since=} {--workspace=ws-emk-dhaka} {--output=}`
- **Delta Scope:** Queries all applications submitted to the Hub (`leads` table where `submitted_at >= $cutoffTimestamp` and `workspace_id = $workspaceId`).
- **CSV Formula Injection Sanitization (T-11-12):** Automatically prepends single quote (`'`) to any cell beginning with `=`, `+`, `-`, `@`, `\t`, or `\r`.
- **File Protection:** Restricts generated file permissions to `0600` via `@chmod($outputPath, 0600)`.
- **Audit Logging:** Emits immutable audit event `hub_delta_exported` logging record counts and cutoff timestamp.

### 4. Comprehensive Production Cutover Runbook
- **Location:** `.planning/phases/11-zoho-migration-and-reconciliation/CUTOVER-RUNBOOK.md`
- **7-Step Cutover Protocol:**
  1. *Step 1: Pre-Cutover Staging Dry Run* (100% reconciliation match on counts, emails, dates, attachments).
  2. *Step 2: Staff Training Sign-Off* (5 EMK Center Dhaka staff users completed simulated workflows).
  3. *Step 3: Form Freeze at T-0 (20:00 BST)* (disable Zoho submit button, display maintenance notice).
  4. *Step 4: Final Delta Ingestion* (import records from prior 48 hours, provision inactive portal accounts).
  5. *Step 5: DNS & Web Server Switch* (point canonical route to `/spaces/emk-dhaka/apply`).
  6. *Step 6: Queue & Worker Ignition* (launch Laravel Horizon queues and scheduler cron).
  7. *Step 7: Live Production Smoke Test* (traverse application lifecycle with `TEST-MEMBER-001`, then purge).
- **Quantifiable Rollback Triggers:**
  - **TR-01**: Submission Failure Rate > 2%
  - **TR-02**: Historical Data Discrepancy > 0.1%
  - **TR-03**: Any Cross-Workspace Data Leakage (DEC-01 breach)
  - **TR-04**: Notification Email Delivery Failure > 5%
  - **TR-05**: Document Storage Corruption (decryption/hash failure)
- **30-Minute Emergency Rollback Sequence:** 5-step procedure to re-activate legacy Zoho Forms, revert DNS, export Hub delta via `zoho:export-hub-delta`, import into Zoho CRM, and broadcast incident handoff.

---

## Verification Results

### Automated Test Suite
Test suite `tests/Feature/ZohoDryRunAndCutoverTest.php` executed with 100% success:
- `✓ dry run engine simulates ingestion and guarantees zero database mutations`
- `✓ dry run engine detects validation errors without persisting`
- `✓ portal account provisioning stages inactive passwordless accounts`
- `✓ artisan zoho dry run command executes successfully`
- `✓ artisan zoho provision portal accounts command executes successfully`
- `✓ hub delta export service extracts records submitted after cutoff`
- `✓ hub delta export service sanitizes csv formula injection`
- `✓ artisan zoho export hub delta command writes sanitized csv and logs audit`
- `✓ cutover runbook codifies seven steps and rollback triggers`

**Full Phase 11 Regression Suite:**
- `ZohoDryRunAndCutoverTest.php`: 9 tests, 73 assertions (PASS)
- `ZohoReconciliationReportTest.php`: 13 tests, 75 assertions (PASS)
- `ZohoAttachmentMigrationTest.php`: 15 tests, 62 assertions (PASS)
- `ZohoDataIngestionTest.php`: 11 tests, 75 assertions (PASS)
- **Total: 48 tests, 285 assertions, 0 failures.**

---

## Self-Check: PASSED

1. **Created Files Exist:**
   - `FOUND: app/Domain/Migration/Services/ZohoDryRunEngine.php`
   - `FOUND: app/Domain/Migration/Services/PortalAccountProvisioningService.php`
   - `FOUND: app/Domain/Migration/Services/HubDeltaExportService.php`
   - `FOUND: app/Domain/Migration/Commands/ZohoDryRunCommand.php`
   - `FOUND: app/Domain/Migration/Commands/ZohoProvisionPortalAccountsCommand.php`
   - `FOUND: app/Domain/Migration/Commands/ZohoExportHubDeltaCommand.php`
   - `FOUND: .planning/phases/11-zoho-migration-and-reconciliation/CUTOVER-RUNBOOK.md`
   - `FOUND: tests/Feature/ZohoDryRunAndCutoverTest.php`

2. **Commits Exist in History:**
   - `FOUND: a34cd3c - feat(phase-11): transactional dry-run engine and inactive portal account provisioner`
   - `FOUND: ea51d77 - feat(phase-11): cutover delta export engine, rollback tool, and cutover runbook`
