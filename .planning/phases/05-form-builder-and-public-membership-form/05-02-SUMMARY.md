---
phase: 05-form-builder-and-public-membership-form
plan: 02
subsystem: form-builder-admin-and-official-form-seeder
tags:
  - forms
  - tenancy
  - livewire
  - form-builder
  - seeder
  - 31-field-form
  - permissions
  - non-delegable
  - bkash
  - youth-consent
status: complete
requires:
  - "05-01"
provides:
  - form-index-livewire-component
  - form-builder-livewire-component
  - form-embed-generator
  - default-membership-form-seeder
  - canonical-31-field-form-definition
affects:
  - app/Livewire/Forms/FormIndex.php
  - app/Livewire/Forms/FormBuilder.php
  - resources/views/livewire/forms/form-index.blade.php
  - resources/views/livewire/forms/form-builder.blade.php
  - database/seeders/DefaultMembershipFormSeeder.php
  - database/seeders/DatabaseSeeder.php
  - routes/web.php
  - tests/Feature/FormBuilderAdminTest.php
  - tests/Feature/DefaultMembershipFormSeederTest.php
tech-stack:
  added: []
  patterns:
    - "Livewire 3 components FormIndex and FormBuilder implementing ValidatesWorkspaceOnHydration for tamper-proof tenant isolation"
    - "Spatie permission-gated administrative workflows with strictly non-delegable managerial boundaries on forms.publish and forms.archive per MODULE-REGISTRY.md"
    - "Dynamic embed code generation offering standalone public permalinks, responsive iFrame snippets, and external HTML/CSS integration blocks"
    - "Canonical 31-field database seeder codifying the complete data dictionary, Bangladesh 8 divisions, 64 partitioned districts, under-18 consent logic, and 10-char uppercase bKash rule"
key-files:
  created:
    - app/Livewire/Forms/FormIndex.php
    - app/Livewire/Forms/FormBuilder.php
    - resources/views/livewire/forms/form-index.blade.php
    - resources/views/livewire/forms/form-builder.blade.php
    - database/seeders/DefaultMembershipFormSeeder.php
    - tests/Feature/FormBuilderAdminTest.php
    - tests/Feature/DefaultMembershipFormSeederTest.php
  modified:
    - database/seeders/DatabaseSeeder.php
    - routes/web.php
decisions:
  - "Enforced strict non-delegable managerial authorization on FormIndex and FormBuilder: publish() and archive() reject delegated staff without forms.publish / forms.archive with HTTP 403 per MODULE-REGISTRY.md."
  - "Protected both Livewire components against cross-tenant tampering via ValidatesWorkspaceOnHydration and hydrate checks on workspace and Form models."
  - "Explicitly provided UUID generation in DefaultMembershipFormSeeder to ensure reliable execution when DatabaseSeeder runs with WithoutModelEvents."
  - "Implemented full 31-field schema in DefaultMembershipFormSeeder with exact regex rules, 8 divisions, 64 districts partitioned by division, under-18 conditional consent rule per DEC-09, and 10-character uppercase bKash validation per DEC-05."
metrics:
  duration: 15m
  completed_date: "2026-11-04"
actuals:
  tokens: 31631
  tasks: 2
  commits: 2
  plan_head_before: "1778e79f8188bf58f7c199d481bd930c26c4f641"
  plan_head_after: "3e27bb2a95c32baae085698b68aa4a8f9f1165bb"
---

# Phase 5 Plan 02: Form Builder Admin Management Livewire Components, Embed Generator, and Official 31-Field Membership Application Seeder Summary

Complete implementation of the administrative Form Builder interfaces in Livewire (`FormIndex`, `FormBuilder`), multi-workspace tenant isolation, managerial non-delegable action security, embed code generators, and the official 31-field Membership Application Form database seeder matching FIELD-DICTIONARY.md per FORM-01, FORM-02, DEC-01, DEC-02, DEC-05, and DEC-09.

## Performance & Execution Overview

- **Tasks Completed:** 2 / 2
- **Test Suite Status:** 174 tests (1023 assertions) passing across the full project test suite; 22 tests (125 assertions) across Plan 05-02 test suites.
- **Commits:** 2 atomic feature commits.

## Accomplishments by Task

### Task 1: Form Builder Admin Management Livewire Components and Embed Generator
- Created `App\Livewire\Forms\FormIndex`:
  - Mounted at `/crm/{workspace}/forms` under `crm.forms.index`.
  - Scoped to workspace with `ValidatesWorkspaceOnHydration` and `WithPagination`.
  - Gated by `forms.view` permission; superadmin and workspace managers can access appropriately.
  - Lists forms with submission counts, publication badges, and action buttons.
  - Interactive "Create New Form" modal requiring `forms.create`.
  - Embed code modal generating public permalink, responsive iFrame snippet, and HTML/CSS snippet.
  - Managerial `publish()` and `archive()` actions strictly protected (non-delegable).
- Created `App\Livewire\Forms\FormBuilder`:
  - Mounted at `/crm/{workspace}/forms/{form}/builder` under `crm.forms.builder`.
  - Gated by `forms.edit` permission.
  - Visual field editor allowing adding, editing, reordering (move up/down), and deleting fields.
  - Configuration of field key, label, input type from `FormSchemaEngine::getFieldCatalog()`, requirements, casing, min/max bounds, regex, select/radio options, datasets, cascading parent fields, and file upload parameters.
  - Zero-eval conditional visibility configuration (`field`, `operator`, `value`).
  - Integrated embed code generator modal.
  - Non-delegable managerial `publish()` and `archive()` actions aborting with HTTP 403 when invoked by delegated staff.
- Designed responsive Blade templates:
  - `resources/views/livewire/forms/form-index.blade.php`
  - `resources/views/livewire/forms/form-builder.blade.php`
- Registered routes in `routes/web.php` inside the `/crm/{workspace}` route group with `workspace.access`.
- Created comprehensive feature tests in `tests/Feature/FormBuilderAdminTest.php` covering permissions, tenancy isolation, schema manipulation, embed generation, and non-delegable managerial boundaries.
- **Commit:** `078d763`

### Task 2: Official 31-Field Membership Application Form Definition and Database Seeder
- Created `database/seeders/DefaultMembershipFormSeeder.php`:
  - Targets `workspace_id = 'ws-emk-dhaka'`, `slug = 'membership-application'`, `is_published = true`, `is_default = true`, `version = '1.0-31f'`.
  - Fully codifies all 31 canonical fields specified in `FIELD-DICTIONARY.md` Section 3:
    1. `first_name` (text, required, min:2, max:50, regex: `^[A-Za-z\s'\-]{2,50}$`)
    2. `middle_name` (text, optional, max:50, regex: `^[A-Za-z\s'\-]{1,50}$`)
    3. `last_name` (text, required, min:2, max:50, regex: `^[A-Za-z\s'\-]{2,50}$`)
    4. `american_space` (select, required, 6 American Spaces)
    5. `sex` (select, required: male, female, na)
    6. `date_of_birth` (date, required, format Y-m-d, before:today, after:1900-01-01)
    7. `parental_consent_path` (file, conditional required if age_years < 18 per DEC-09, mimes: pdf, jpg, jpeg, png, max 4096KB)
    8. `address_house_no` (text, required, min:1, max:100, regex: `^[A-Za-z0-9\s,\-\/\.#]{1,100}$`)
    9. `address_road_no` (text, optional, max:100, regex: `^[A-Za-z0-9\s,\-\/\.#]{1,100}$`)
    10. `address_area` (text, required, min:2, max:100, regex: `^[A-Za-z0-9\s,\-\.]{2,100}$`)
    11. `address_zip_code` (text, required, min:3, max:10, regex: `^[A-Za-z0-9\s\-]{3,10}$`)
    12. `address_division` (select, required, 8 Bangladesh divisions)
    13. `address_district` (cascading_select, required, 64 districts partitioned by division)
    14. `contact_number` (text, required, regex: `^\+8801[3-9]\d{8}$`)
    15. `email` (email, required, max:150)
    16. `website` (text, optional, max:200)
    17. `occupation` (select, required, 8 options)
    18. `institution_study` (text, required, min:2, max:150)
    19. `highest_certification` (select, required, 9 options)
    20. `area_of_subject` (text, required, min:2, max:150)
    21. `has_us_experience` (radio, required: 1/0)
    22. `us_experience_location` (text, conditional on has_us_experience = 1, max:150)
    23. `is_american_alumni` (radio, required: 1/0)
    24. `american_institution_name` (text, conditional on is_american_alumni = 1, max:150)
    25. `employer_name` (text, optional, max:150)
    26. `job_title` (text, optional, max:100)
    27. `work_phone` (text, optional, regex: `^\+8801[3-9]\d{8}$`)
    28. `identity_document_paths` (multi_file, required, min:1, max:2, mimes: pdf, jpg, jpeg, doc, docx, png, max 4096KB)
    29. `photo_path` (file, required, studio passport guidelines, mimes: jpg, jpeg, png, max 4096KB)
    30. `bkash_txid` (text, required, size:10, uppercase, regex: `^[A-Z0-9]{10}$` per DEC-05)
    31. `terms_agreed` (checkbox, required, accepted)
  - Configured form settings with confirmation email, allowed spaces, and instructions for identity documents, studio photos, payments, and minor consent.
- Updated `database/seeders/DatabaseSeeder.php` to include `DefaultMembershipFormSeeder`.
- Created feature test `tests/Feature/DefaultMembershipFormSeederTest.php` asserting form creation, 31 field names, geographic integrity, youth consent rule, and bKash 10-char uppercase constraint.
- Ran full test suite: 174 tests passing with 1023 assertions.
- **Commit:** `3e27bb2`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Explicit UUID assignment in DefaultMembershipFormSeeder**
- **Found during:** Task 2 verification with `DatabaseSeeder`
- **Issue:** `DatabaseSeeder` runs with `WithoutModelEvents`, which disabled Eloquent model booted event creating `Str::uuid()`, triggering a NOT NULL constraint violation on `forms.uuid`.
- **Fix:** Added explicit UUID derivation (`$existing ? $existing->uuid : (string) Str::uuid()`) directly in the seeder payload.
- **Files modified:** `database/seeders/DefaultMembershipFormSeeder.php`
- **Commit:** `3e27bb2`

## Self-Check: PASSED

1. Created files exist:
   - `app/Livewire/Forms/FormIndex.php` (FOUND)
   - `app/Livewire/Forms/FormBuilder.php` (FOUND)
   - `resources/views/livewire/forms/form-index.blade.php` (FOUND)
   - `resources/views/livewire/forms/form-builder.blade.php` (FOUND)
   - `database/seeders/DefaultMembershipFormSeeder.php` (FOUND)
   - `tests/Feature/FormBuilderAdminTest.php` (FOUND)
   - `tests/Feature/DefaultMembershipFormSeederTest.php` (FOUND)

2. Commits exist in git history:
   - `078d763` (FOUND)
   - `3e27bb2` (FOUND)
