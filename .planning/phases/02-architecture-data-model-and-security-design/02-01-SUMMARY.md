---
phase: 02-architecture-data-model-and-security-design
plan: 01
subsystem: architecture
tags:
  - modular-architecture
  - module-registry
  - declared-actions
  - zero-eval
  - schema-spec
  - mysql84
  - er-diagram
  - multi-tenant
requires:
  - ARCH-01
  - ARCH-02
provides:
  - MODULE-REGISTRY.md (central static module catalog & 38 declared actions)
  - SCHEMA-SPEC.md (complete MySQL 8.4 DDL blueprint for 13 relational tables)
affects:
  - phase-03 (application skeleton generation & service providers)
  - phase-04 (database migrations & Spatie permission seeding)
  - phase-05 (form builder & public application endpoints)
  - phase-06 (leads CRM & tri-state verification engine)
tech-stack:
  added: []
  patterns:
    - Central static module registry singleton with zero runtime code evaluation (DEC-02)
    - Decoupled physical American Spaces directory from logical tenant workspaces (DEC-01)
    - Multi-guard user authentication (web vs member)
    - Dynamic membership terms schema without hardcoded durations (DEC-08)
    - Append-only immutable audit trail (DEC-11)
    - Concurrency-safe atomic sequence generator for Member IDs
key-files:
  created:
    - .planning/phases/02-architecture-data-model-and-security-design/MODULE-REGISTRY.md
    - .planning/phases/02-architecture-data-model-and-security-design/SCHEMA-SPEC.md
  modified: []
decisions:
  - "Enforce static ModuleRegistry singleton cataloging 8 core modules and 38 declared actions with zero dynamic code evaluation per DEC-02 (D-02)."
  - "Lock 16 managerial actions as strictly non-delegable to prevent staff privilege escalation per T-01-07."
  - "Decouple physical spaces directory from logical tenant workspaces, launching with EMK Center Dhaka (ws-emk-dhaka) per DEC-01 (D-01)."
  - "Enforce uq_leads_bkash_txid unique constraint on 10-char uppercase bKash TxID per DEC-05 (D-05)."
  - "Configure dynamic terms schema in membership_terms without hardcoded durations per DEC-08 (D-08)."
  - "Preserve zoho_id unique indexed columns across users, leads, members, and invoices for legacy migration reconciliation."
metrics:
  duration: 8m
  completed_date: "2026-11-03"
status: complete
actuals:
  tokens: 14750
  tasks: 2
  commits: 2
  plan_head_before: e9b7eb9
  plan_head_after: 2be9c8e
---

# Phase 02 Plan 01: Central Module Registry & Relational ER Schema Summary

**One-Liner:** Static 8-module catalog with 38 declared actions and zero-eval execution invariant paired with exhaustive 13-table MySQL 8.4 relational ER schema.

---

## Executive Summary

Phase 02 Plan 01 delivers the foundational architectural contracts and relational database baseline for the EMK Membership CRM platform embedded within the Laravel 12 Hub monolith. 

The implementation satisfies requirements **ARCH-01** and **ARCH-02** by producing two comprehensive design specifications:
1. `MODULE-REGISTRY.md`: Establishes the static central module registry, catalogues the 8 bounded domain modules and exactly 38 declared actions in Spatie permission format (`{module}.{action}`), mandates the zero dynamic code evaluation invariant prohibiting `eval()`, isolates the 8 delegable vs 16 strictly non-delegable managerial actions per **T-01-07**, and specifies the `ModuleRegistry` singleton contract and workspace-level enablement middleware.
2. `SCHEMA-SPEC.md`: Defines the complete MySQL 8.4 relational ER schema for 13 database tables, decouples physical American Spaces directory (`spaces`) from logical tenant boundaries (`workspaces`) launching with `ws-emk-dhaka` per **DEC-01 (D-01)**, enforces fraud prevention with `uq_leads_bkash_txid` unique constraint on 10-char uppercase bKash TxIDs per **DEC-05 (D-05)**, configures dynamic terms without hardcoded durations per **DEC-08 (D-08)**, establishes append-only auditing per **DEC-11 (D-11)**, and preserves legacy `zoho_id` migration columns.

---

## Tasks Completed

| Task | Name | Type | Commit | Key Files Created |
|---|---|---|---|---|
| **1** | Central Module Registry Contract, Zero-Eval Architecture, and Declared Actions Catalog | `tracer` | `b4177a6` | `.planning/phases/02-architecture-data-model-and-security-design/MODULE-REGISTRY.md` |
| **2** | Relational ER Schema, Table Specifications, and Migration Integrity Baseline | `auto` | `2be9c8e` | `.planning/phases/02-architecture-data-model-and-security-design/SCHEMA-SPEC.md` |

---

## Key Deliverables & Specifications Established

### 1. Module Registry & Declared Actions (`MODULE-REGISTRY.md`)
- **Zero-Eval Invariant:** Complete prohibition of `eval()`, `create_function()`, dynamic variable class instantiations, and arbitrary runtime plugin loading. Enforced via Pest architecture tests.
- **8 Core Bounded Modules:**
  - `forms`: Dynamic form builder, 31-field validation contracts, and public entry staging.
  - `leads`: Inbound application lifecycle, 31-field profiles, and tri-state verification streams.
  - `members`: Converted member master profiles, dynamic term validity, and ID badge lifecycle.
  - `member_portal`: Read-only member self-service portal and dedicated email change request workflow.
  - `invoices`: Post-conversion billing records and payment reconciliation.
  - `dashboard_reports`: Workspace-scoped operational KPI dashboards and metric exports.
  - `platform_services`: Staff account provisioning, dynamic term configuration, role delegation, and audit logging.
  - `migration`: Legacy Zoho Forms and Zoho CRM ingestion, field transformation, and reconciliation.
- **38 Declared Actions Catalog:** Full catalog in `{module}.{action}` syntax matching `DELEGATION-MATRIX.md`.
- **Delegation Security Isolation (T-01-07):** Hardcoded allowlist of 8 Delegable Actions vs 16 Strictly Non-Delegable Managerial Actions locked against staff privilege escalation.
- **Service Contract:** `ModuleRegistryInterface` and `ModuleRegistry` singleton class blueprint with O(1) indexed lookup.

### 2. MySQL 8.4 Relational ER Schema (`SCHEMA-SPEC.md`)
- **13 Relational Tables:**
  1. `spaces`: Physical American Spaces directory (EMK Center, American Center, and 4 Corners).
  2. `workspaces`: Logical tenant boundary launching with `ws-emk-dhaka`.
  3. `users`: Multi-guard authentication (`web` for staff, `member` for portal).
  4. `membership_terms`: Dynamic term configuration (`duration_months`, `fee_bdt`).
  5. `leads`: Inbound application records with all 31 fields, tri-state verification columns, and `deleted_at`.
  6. `lead_attachments`: Private document store with SHA-256 hashes and youth protection flag.
  7. `lead_verifications`: Reviewer audit records for Payment, Document, and Photo verification streams.
  8. `members`: Converted member profiles with start date defaulting to Manager approval date per DEC-08.
  9. `member_id_sequences`: Monotonic sequence counter per space code and year.
  10. `invoices`: Post-conversion billing records linked to members and leads.
  11. `id_cards`: Physical badge tracking (provided_for_printing, card_printed_ready_for_pickup, delivered).
  12. `member_change_requests`: Self-service profile update requests including dedicated email change workflow.
  13. `audit_logs`: Append-only immutable security event trail excluding sensitive secrets.
- **Constraint Matrix & Delete Rules:**
  - `RESTRICT` on core tenancy anchors (`spaces`, `workspaces`, `users`, `membership_terms`, `members`).
  - `CASCADE` on child entities (`lead_attachments`, `lead_verifications`, `member_change_requests`).
  - `SET NULL` on optional reference links (`invoices.lead_id`, `member_change_requests.reviewed_by_user_id`).
- **bKash Fraud Prevention:** Enforces `UNIQUE KEY uq_leads_bkash_txid` on `CHAR(10)` uppercase column.
- **Migration Idempotency:** Preserves unique indexed `zoho_id` across `users`, `leads`, `members`, and `invoices`.

---

## Deviations from Plan

None — plan executed exactly as written.

---

## Verification & Self-Check

### 1. Created Files
- `[x]` `d:/EMK_Protal/.planning/phases/02-architecture-data-model-and-security-design/MODULE-REGISTRY.md` — FOUND
- `[x]` `d:/EMK_Protal/.planning/phases/02-architecture-data-model-and-security-design/SCHEMA-SPEC.md` — FOUND

### 2. Commit Existence
- `[x]` `b4177a6`: feat(02-01): define central module registry contract and declared actions catalog — FOUND
- `[x]` `2be9c8e`: feat(02-01): define relational ER schema and MySQL 8.4 DDL specifications — FOUND

### 3. Automated Assertions
- `[x]` Task 1 automated verify command: PASSED
- `[x]` Task 2 automated verify command: PASSED
- `[x]` Tracer feedback gate: PASSED

## Self-Check: PASSED
