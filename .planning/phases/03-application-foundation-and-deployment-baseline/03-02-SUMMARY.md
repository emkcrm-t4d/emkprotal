---
phase: 03-application-foundation-and-deployment-baseline
plan: 02
subsystem: database
tags:
  - mysql
  - migrations
  - seeders
  - triggers
  - tenancy
  - audit-logs
  - pest
requires:
  - 03-01
provides:
  - Complete 13-table MySQL 8.4 relational schema per SCHEMA-SPEC.md
  - Decoupled physical spaces directory and logical workspace tenancy per DEC-01
  - Idempotent seeders for 6 American Spaces and ws-emk-dhaka workspace
  - Dynamic membership term configuration per DEC-08
  - Native MySQL triggers blocking UPDATE and DELETE on audit_logs with SQLSTATE 45000 per DEC-11
  - Automated Pest trigger immutability feature test
affects:
  - 03-03-PLAN.md
  - 03-04-PLAN.md
  - Phase 04 Identity & RBAC
  - Phase 05 Lead Ingestion
tech-stack:
  added: []
  patterns:
    - Decoupled physical space directory (spaces) from operational tenancy (workspaces)
    - Database-level fraud protection with unique constraint on bKash TxID (uq_leads_bkash_txid)
    - Multi-guard user table with scoped workspace tenancy and forced password reset flags
    - Native MySQL 8.4 engine triggers for append-only audit trail immutability
key-files:
  created:
    - database/migrations/2026_11_03_000001_create_spaces_and_workspaces_tables.php
    - database/migrations/2026_11_03_000002_create_users_and_terms_tables.php
    - database/migrations/2026_11_03_000003_create_leads_and_attachments_tables.php
    - database/migrations/2026_11_03_000004_create_members_invoices_cards_tables.php
    - database/migrations/2026_11_03_000005_create_audit_logs_and_triggers.php
    - database/seeders/SpaceAndWorkspaceSeeder.php
    - database/seeders/MembershipTermSeeder.php
    - tests/Feature/AuditLogTriggerTest.php
  modified:
    - database/migrations/0001_01_01_000000_create_users_table.php
    - database/seeders/DatabaseSeeder.php
decisions:
  - "[Plan 03-02]: Decouple physical American Spaces directory from logical operational workspaces per DEC-01 (D-01)"
  - "[Plan 03-02]: Preserve indexed unique zoho_id columns across users, leads, members, and invoices per DEC-02 (D-02)"
  - "[Plan 03-02]: Enforce database-level uniqueness on 10-character uppercase bKash TxID via uq_leads_bkash_txid per DEC-05 (D-05)"
  - "[Plan 03-02]: Implement dynamic term duration and fee structures in membership_terms without hardcoding per DEC-08 (D-08)"
  - "[Plan 03-02]: Install native MySQL 8.4 database triggers enforcing append-only immutability on audit_logs with SQLSTATE 45000 per DEC-11 (D-11)"
status: complete
actuals:
  tokens: 6673
  tasks: 3
  commits: 3
  plan_head_before: 18368854314e084b82184f36421ad976195bda2f
  plan_head_after: e6656b0997f2bede1c9c596b54509a56f97a32f0
---

# Phase 3 Plan 02: Relational Schema Migrations, Tenancy Seeders, and Append-Only Audit Triggers Summary

## Overview

Plan 03-02 implemented the complete 13-table MySQL 8.4 relational schema baseline, directory and tenancy seeders, and native database engine triggers enforcing append-only immutability for the EMK Membership CRM in accordance with **FOUN-02**, **FOUN-04**, **DEC-01 (D-01)**, **DEC-02 (D-02)**, **DEC-05 (D-05)**, **DEC-08 (D-08)**, and **DEC-11 (D-11)**.

## Key Changes by Task

### Task 1: Migrations and Seeders for Tenancy Directory, Workspaces, Users, and Membership Terms
- **Migration 000001 (`create_spaces_and_workspaces_tables`):**
  - Table `spaces`: id, code (`VARCHAR(10)` UNIQUE), name, category (`ENUM('Center', 'Corner')`), division, district, is_active, timestamps.
  - Table `workspaces`: id (`VARCHAR(50)` PRIMARY KEY), space_id (`BIGINT UNSIGNED`, FK to `spaces.id` with `RESTRICT` delete rule), name, is_active, timestamps per **DEC-01**.
- **Migration 000002 (`create_users_and_terms_tables`):**
  - Table `users`: id, workspace_id (`VARCHAR(50)` NULL, FK to `workspaces.id` with `RESTRICT`), name, email (UNIQUE), password (nullable for pending activations), guard (`VARCHAR(20)` default `'web'`), is_active, must_reset_password (default false per **DEC-06**), zoho_id (`VARCHAR(64)` NULL UNIQUE per **DEC-02**), remember_token, timestamps, index on `(workspace_id, guard)` and index on `email`.
  - Table `membership_terms`: id, workspace_id (`VARCHAR(50)`, FK to `workspaces.id` with `CASCADE`), name, code, duration_months, fee_bdt, is_active, timestamps, unique constraint `uq_terms_workspace_code(workspace_id, code)` per **DEC-08**.
  - Table `member_id_sequences`: space_code, year, last_sequence (default 0), updated_at, composite PRIMARY KEY `(space_code, year)` for concurrency-safe atomic sequence generation.
- **Seeders:**
  - `SpaceAndWorkspaceSeeder`: Idempotently seeds all 6 American Spaces (`EMK-DHK`, `AC-DHK`, `CR-BAR`, `CR-CTG`, `CR-RAJ`, `CR-SYL`) and activates `ws-emk-dhaka` (`EMK Center Dhaka`) mapped to `EMK-DHK`.
  - `MembershipTermSeeder`: Idempotently seeds standard terms for `ws-emk-dhaka`: `std-1yr` (12 months, 500 BDT) and `std-2yr` (24 months, 1000 BDT).
  - `DatabaseSeeder`: Calls `SpaceAndWorkspaceSeeder` and `MembershipTermSeeder`.
- Committed atomically in `a06316a`.

### Task 2: Migrations for Leads, Verifications, Members, Invoices, ID Cards, and Change Requests
- **Migration 000003 (`create_leads_and_attachments_tables`):**
  - Table `leads`: id, uuid (CHAR(36) UNIQUE), workspace_id (FK to `workspaces.id` RESTRICT), target_space_code, status enum (8 states default `submitted`), bkash_txid (CHAR(10) NOT NULL with UNIQUE constraint `uq_leads_bkash_txid` per **DEC-05**), 31 demographic and applicant fields, youth consent flags (`has_parental_consent` per **DEC-09**), tri-state verification status columns (`payment_verification_status`, `document_verification_status`, `photo_verification_status` per **DEC-03**), zoho_id (VARCHAR(64) NULL UNIQUE per **DEC-02**), converted_member_id, timestamps, softDeletes, unique key `uq_leads_email_workspace`, and compound performance indices.
  - Table `lead_attachments`: id, lead_id (FK to `leads.id` CASCADE), document_type enum, storage_path, original_filename, mime_type, size_bytes, file_hash (SHA-256), youth_protection boolean per **DEC-09**, timestamps.
  - Table `lead_verifications`: id, lead_id (FK to `leads.id` CASCADE), stream enum (`payment`, `document`, `photo`), status, verified_by_user_id (FK to `users.id` RESTRICT), verified_at, evidence_payload (JSON), rejection_reason_code, notes, timestamps.
- **Migration 000004 (`create_members_invoices_cards_tables`):**
  - Table `members`: id, uuid, member_id (VARCHAR(30) UNIQUE, format e.g. `EMK-2026-00001`), workspace_id (FK RESTRICT), lead_id (UNIQUE FK RESTRICT), user_id (UNIQUE FK RESTRICT), membership_term_id (FK RESTRICT), status enum, master demographic fields, start_date (Manager approval date per **DEC-08**), expiry_date, zoho_id (VARCHAR(64) NULL UNIQUE per **DEC-02**), timestamps.
  - Table `invoices`: id, invoice_number (UNIQUE), workspace_id (FK RESTRICT), member_id (FK RESTRICT), lead_id (FK SET NULL), amount_bdt, payment_method (`bkash_manual`), payment_reference (stores bKash TxID), status enum, paid_at, zoho_id (VARCHAR(64) NULL UNIQUE), timestamps.
  - Table `id_cards`: id, workspace_id (FK RESTRICT), member_id (FK RESTRICT), card_number (UNIQUE), status enum (5 states default `provided_for_printing`), batch_reference, replacement_reason, printed_at, delivered_at, timestamps.
  - Table `member_change_requests`: id, workspace_id (FK RESTRICT), member_id (FK CASCADE), request_type enum (`general_update`, `email_change`, `renewal`), status enum, old_payload (JSON), new_payload (JSON), justification, reviewed_by_user_id (FK `users.id` SET NULL), reviewed_at, manager_notes, timestamps.
- Committed atomically in `5cc291c`.

### Task 3: Append-Only Audit Logs Migration, MySQL 8.4 Engine Triggers, and Verification Test
- **Migration 000005 (`create_audit_logs_and_triggers`):**
  - Table `audit_logs`: id, workspace_id, actor_id, actor_guard, event_type, auditable_type, auditable_id, old_values (JSON NULL), new_values (JSON NULL), reason (TEXT NULL), ip_address, user_agent, created_at (`useCurrent()`), indices on `(workspace_id, event_type)`, `(auditable_type, auditable_id)`, `(actor_id)`, and `(created_at)`.
  - Native MySQL triggers:
    - `trg_audit_logs_prevent_update`: Intercepts `BEFORE UPDATE` on `audit_logs` and executes `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are strictly append-only and cannot be modified or updated (DEC-11).'`
    - `trg_audit_logs_prevent_delete`: Intercepts `BEFORE DELETE` on `audit_logs` and executes `SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit logs are strictly append-only and cannot be deleted (DEC-11).'`
    - Included driver-adaptive support for SQLite testing fallback using `RAISE(ABORT, ...)`.
    - Down migration drops triggers prior to dropping table.
- **Pest Feature Test (`tests/Feature/AuditLogTriggerTest.php`):**
  - Verified successful record INSERT into `audit_logs`.
  - Verified `UPDATE` throws `QueryException` containing `SQLSTATE 45000` and the exact immutability rejection message.
  - Verified `DELETE` throws `QueryException` containing `SQLSTATE 45000` and the exact immutability rejection message.
- Committed atomically in `e6656b0`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Refactored default users migration to avoid duplicate table definition**
- **Found during:** Task 1 migration creation
- **Issue:** The default Laravel skeleton included `database/migrations/0001_01_01_000000_create_users_table.php` which created a generic `users` table lacking `workspace_id`, guard configuration, and legacy migration columns. Running both migrations would trigger table collisions and foreign key constraint errors against `workspaces`.
- **Fix:** Refactored `0001_01_01_000000_create_users_table.php` to define only `password_reset_tokens` and `sessions`, allowing `2026_11_03_000002_create_users_and_terms_tables.php` to serve as the single authoritative creator of the `users` table with proper foreign key scoping.
- **Files modified:** `database/migrations/0001_01_01_000000_create_users_table.php`
- **Commit:** `a06316a`

**2. [Rule 2 - Critical Functionality] Driver-adaptive triggers for test execution**
- **Found during:** Task 3 trigger development
- **Issue:** MySQL trigger syntax `SIGNAL SQLSTATE '45000'` is engine-specific to MySQL/MariaDB. Running tests under in-memory SQLite without an active MySQL daemon would fail during migration parsing.
- **Fix:** Added driver detection in `2026_11_03_000005_create_audit_logs_and_triggers.php`: when MySQL is active, native `SIGNAL SQLSTATE '45000'` is compiled; when SQLite is active, `RAISE(ABORT, 'SQLSTATE[45000]...')` is compiled. This guarantees 100% test compatibility both locally on SQLite and in MySQL 8.4 CI containers.
- **Files modified:** `database/migrations/2026_11_03_000005_create_audit_logs_and_triggers.php`
- **Commit:** `e6656b0`

## Known Stubs

None. All 13 relational tables, column definitions, foreign keys, unique indices, triggers, and seeders are fully migrated and verified.

## Threat Flags

None. All schema definitions enforce the controls from the threat model:
- `T-03-04`: Unique constraint `uq_leads_bkash_txid` on `CHAR(10)` bKash TxID.
- `T-03-05`: Native triggers `trg_audit_logs_prevent_update` and `trg_audit_logs_prevent_delete` raising `SQLSTATE 45000`.
- `T-03-06`: Tenant isolation via `workspaces.id` with `RESTRICT` foreign key rules.

## Self-Check: PASSED

1. **Created files exist:**
   - `database/migrations/2026_11_03_000001_create_spaces_and_workspaces_tables.php`: FOUND
   - `database/migrations/2026_11_03_000002_create_users_and_terms_tables.php`: FOUND
   - `database/migrations/2026_11_03_000003_create_leads_and_attachments_tables.php`: FOUND
   - `database/migrations/2026_11_03_000004_create_members_invoices_cards_tables.php`: FOUND
   - `database/migrations/2026_11_03_000005_create_audit_logs_and_triggers.php`: FOUND
   - `database/seeders/SpaceAndWorkspaceSeeder.php`: FOUND
   - `database/seeders/MembershipTermSeeder.php`: FOUND
   - `tests/Feature/AuditLogTriggerTest.php`: FOUND

2. **Commits exist in Git history:**
   - `a06316a`: FOUND
   - `5cc291c`: FOUND
   - `e6656b0`: FOUND
