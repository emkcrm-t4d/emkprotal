---
phase: 05-form-builder-and-public-membership-form
plan: 01
subsystem: form-builder-core-and-tracer
tags:
  - forms
  - tenancy
  - schema-engine
  - validation-compiler
  - tracer
  - bkash
  - youth-consent
status: complete
requires:
  - "04-05"
provides:
  - forms-and-form-submissions-schema
  - forms-domain-models
  - form-schema-engine
  - form-validation-compiler
  - public-form-tracer-endpoints
affects:
  - database/migrations/2026_11_04_000002_create_forms_and_form_submissions_tables.php
  - app/Domain/Forms/Models/Form.php
  - app/Domain/Forms/Models/FormSubmission.php
  - app/Domain/Forms/Services/FormSchemaEngine.php
  - app/Domain/Forms/Services/FormValidationCompiler.php
  - routes/web.php
  - tests/Feature/FormTracerTest.php
  - tests/Unit/FormSchemaEngineTest.php
  - tests/Unit/FormValidationCompilerTest.php
tech-stack:
  added: []
  patterns:
    - "Relational forms and form_submissions schema with UUID, tenant foreign keys, soft deletes, and reapplicant support on leads table"
    - "Eloquent models Form and FormSubmission implementing TenantEntityInterface and BelongsToTenant"
    - "Zero-eval declarative dynamic schema engine parsing and validating 13 field types and layout elements per DEC-02"
    - "Dynamic validation rule compiler generating Laravel validator arrays, custom messages, cascading district constraints, and input sanitization"
key-files:
  created:
    - database/migrations/2026_11_04_000002_create_forms_and_form_submissions_tables.php
    - app/Domain/Forms/Models/Form.php
    - app/Domain/Forms/Models/FormSubmission.php
    - app/Domain/Forms/Services/FormSchemaEngine.php
    - app/Domain/Forms/Services/FormValidationCompiler.php
    - tests/Feature/FormTracerTest.php
    - tests/Unit/FormSchemaEngineTest.php
    - tests/Unit/FormValidationCompilerTest.php
  modified:
    - routes/web.php
decisions:
  - "Dropped composite unique constraint uq_leads_email_workspace on leads table and replaced with non-unique index idx_leads_email_workspace to support legitimate reapplicant submissions per FORM-05 and LEAD-07 while application layer guards active duplicates."
  - "Implemented declarative Zero-Eval condition evaluation engine in FormSchemaEngine per DEC-02, strictly prohibiting code execution strings or eval()."
  - "Enforced 100-field upper limit per form schema in FormSchemaEngine to mitigate denial of service vulnerabilities per T-05-02."
  - "Enforced exact 10-character uppercase alphanumeric regex rule for bKash Transaction ID in FormValidationCompiler per DEC-05."
  - "Implemented dynamic youth consent rule requiring parental consent file upload whenever calculated age from date_of_birth is under 18 per DEC-09."
metrics:
  duration: 12m
  completed_date: "2026-11-04"
actuals:
  tokens: 18542
  tasks: 3
  commits: 3
  plan_head_before: "82b7cc365ef4f47e255b59713979747d8f3d4a17"
  plan_head_after: "6ca7a4557a756183886f69b6758257150d5abc7b"
---

# Phase 5 Plan 01: Form Builder Domain Architecture, Dynamic Schema Engine, Validation Compiler, and Tracer Slice Summary

Delivery of the foundational Form Builder domain architecture, relational schema migration for forms and form_submissions, reapplicant support on the leads table, zero-eval FormSchemaEngine, FormValidationCompiler, and the public form rendering and submission tracer slice per FORM-01, DEC-01, DEC-02, DEC-05, and DEC-09.

## Performance & Execution Overview

- **Tasks Completed:** 3 / 3
- **Test Suite Status:** 152 tests (898 assertions) passing across the full project test suite; 28 tests (136 assertions) across Plan 05-01 test suites.
- **Commits:** 3 atomic feature/test commits.

## Accomplishments by Task

### Task 1: End-to-End Form Definition, Public Rendering, and Submission Tracer Slice
- Created database migration `database/migrations/2026_11_04_000002_create_forms_and_form_submissions_tables.php`:
  - `forms` table with UUID, tenant workspace foreign key, unique slug, title, description, JSON schema, settings, publication status, versioning, soft deletes, and performance indexes.
  - `form_submissions` table with UUID, form foreign key, tenant workspace foreign key, lead foreign key, raw JSON payload, status enum, IP/user-agent tracking, and indexes.
  - Updated `leads` table: dropped composite unique constraint `uq_leads_email_workspace` and added non-unique index `idx_leads_email_workspace` to allow rejected applicants to reapply with the same email.
- Created `Form` and `FormSubmission` Eloquent models implementing `TenantEntityInterface` with `BelongsToTenant` and relations.
- Registered public tracer routes in `routes/web.php`:
  - `GET /apply/{slug}`: renders form public view or returns JSON with metadata and schema if published.
  - `POST /apply/{slug}/submit`: validates published state, persists submission payload, and returns HTTP 200 with submission UUID.
- Added feature tests in `tests/Feature/FormTracerTest.php` asserting route access, submission persistence, unpublished 404 responses, and reapplicant support.
- **Commit:** `d21d806`

### Task 2: Form Builder Dynamic Schema Engine and Field Type Catalog
- Created `App\Domain\Forms\Services\FormSchemaEngine`:
  - Full catalog of 13 supported field types and elements: `text`, `email`, `select`, `cascading_select`, `radio`, `checkbox`, `date`, `file`, `multi_file`, `number`, `textarea`, `instructions`, `section_break`.
  - Schema structure validator enforcing required properties, unique field names, valid field types, permitted conditional operators, and a 100-field DoS ceiling (T-05-02).
  - Field normalizer applying default attributes, labels, casing, and limits.
  - Zero-eval conditional visibility evaluator comparing values (`equals`, `not_equals`, `in`, `not_in`, `greater_than`, `less_than`, `age_less_than`, `is_empty`, etc.) with `and`/`or` logic without `eval()` per DEC-02.
  - Safe dynamic age calculation derived from `date_of_birth` using Carbon.
  - Static dataset provider for 8 Bangladesh administrative divisions, 64 districts partitioned by division, and American Spaces.
- Added unit tests in `tests/Unit/FormSchemaEngineTest.php` asserting catalog integrity, normalization, invalid operator rejection, age derivation, and zero-eval safety.
- **Commit:** `5f8a1b7`

### Task 3: Dynamic Validation Rule Compiler and Input Sanitization Pipeline
- Created `App\Domain\Forms\Services\FormValidationCompiler`:
  - Compiles dynamic JSON schemas into standard Laravel validation rule arrays (`required`, `string`, `min`, `max`, `regex`, `email:rfc`, `in`, `mimes`, `accepted`, `date_format`, etc.).
  - Cascading district rules dynamically restricted to selected division per FIELD-DICTIONARY.md Section 4.
  - Conditional youth consent rules requiring parental consent file upload when applicant age derived from `date_of_birth` is under 18 per DEC-09.
  - Strict 10-character uppercase alphanumeric validation rule (`size:10, regex:/^[A-Z0-9]{10}$/`) for bKash Transaction ID per DEC-05.
  - Compiles human-friendly custom error messages per field.
  - Sanitization pipeline providing UTF-8 validation, stripping control characters, whitespace trimming, auto-normalizing bKash TxID to uppercase, and HTML entity escaping against XSS.
- Added unit tests in `tests/Unit/FormValidationCompilerTest.php` asserting rule compilation, cascading restrictions, youth consent logic, bKash format enforcement, custom messages, and sanitization.
- Ran full test suite: 152 tests passing with 898 assertions.
- **Commit:** `6ca7a45`

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

1. Created files exist:
   - `database/migrations/2026_11_04_000002_create_forms_and_form_submissions_tables.php` (FOUND)
   - `app/Domain/Forms/Models/Form.php` (FOUND)
   - `app/Domain/Forms/Models/FormSubmission.php` (FOUND)
   - `app/Domain/Forms/Services/FormSchemaEngine.php` (FOUND)
   - `app/Domain/Forms/Services/FormValidationCompiler.php` (FOUND)
   - `tests/Feature/FormTracerTest.php` (FOUND)
   - `tests/Unit/FormSchemaEngineTest.php` (FOUND)
   - `tests/Unit/FormValidationCompilerTest.php` (FOUND)

2. Commits exist in git history:
   - `d21d806` (FOUND)
   - `5f8a1b7` (FOUND)
   - `6ca7a45` (FOUND)
