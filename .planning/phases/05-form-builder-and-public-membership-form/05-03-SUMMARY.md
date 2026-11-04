---
phase: 05-form-builder-and-public-membership-form
plan: 03
subsystem: public-membership-form-and-reactivity
tags:
  - forms
  - public-portal
  - livewire
  - alpinejs
  - 31-field-form
  - cascading-hierarchy
  - youth-consent
  - bkash
status: complete
requires:
  - "05-02"
provides:
  - public-membership-form-livewire-component
  - public-layout-blade
  - bangladesh-cascading-districts
  - conditional-youth-consent-engine
  - bkash-10char-uppercase-formatter
affects:
  - app/Livewire/Forms/PublicMembershipForm.php
  - resources/views/livewire/forms/public-membership-form.blade.php
  - resources/views/forms/public-layout.blade.php
  - public/forms/templates/EMK-Parental-Consent-Form.pdf
  - routes/web.php
  - tests/Feature/PublicMembershipFormRenderTest.php
  - tests/Feature/PublicFormReactivityTest.php
tech-stack:
  added: []
  patterns:
    - "Clean public layout decoupled from administrative navigation with official American Spaces / EMK Center branding and 256-bit TLS security indicator"
    - "Livewire 3 full-page component mounting published Form model by slug with support for all 31 fields organized in 7 accessible sections"
    - "Reactive division-to-district cascading hierarchy partitioning 64 Bangladesh districts across 8 divisions with automatic clearing on division change"
    - "Dynamic client/server minor consent evaluation per DEC-09 triggering Field 7 upload, amber warning notice, and template download link when age < 18"
    - "Dual-layer bKash Transaction ID validation per DEC-05 enforcing Alpine uppercase formatting and strict 10-char uppercase alphanumeric regex"
key-files:
  created:
    - app/Livewire/Forms/PublicMembershipForm.php
    - resources/views/livewire/forms/public-membership-form.blade.php
    - resources/views/forms/public-layout.blade.php
    - public/forms/templates/EMK-Parental-Consent-Form.pdf
    - tests/Feature/PublicMembershipFormRenderTest.php
    - tests/Feature/PublicFormReactivityTest.php
  modified:
    - routes/web.php
decisions:
  - "Isolated public application shell in resources/views/forms/public-layout.blade.php completely decoupled from CRM admin shell, removing administrative navigation and session leakage risks."
  - "Enforced DEC-09 minor consent logic dynamically: calculating age from DOB via Carbon diffInYears, activating Field 7 with mandatory upload and amber warning banner if age < 18, and hiding/clearing Field 7 if age >= 18."
  - "Enforced DEC-05 bKash Transaction ID standard with dual layers: Alpine.js real-time client transform and server regex size:10 uppercase alphanumeric validation."
  - "Implemented cascading division-to-district restriction across all 8 Bangladesh divisions and 64 districts with automatic reset of invalid districts on division change."
metrics:
  duration: 15m
  completed_date: "2026-11-04"
actuals:
  tokens: 29126
  tasks: 2
  commits: 2
  plan_head_before: "df23a9a326bc9b25b15a93dbb19e446d18ef3ab8"
  plan_head_after: "468702c8643c498348fccd9c61b9ab1d64e87424"
---

# Phase 5 Plan 03: Public Membership Application Livewire Component, Clean Public Layout, and Reactive Cascades Summary

Implementation of the public-facing Membership Application Form Livewire component (`PublicMembershipForm`), isolated public Blade shell (`forms/public-layout.blade.php`), Bangladesh administrative division-to-district cascading hierarchy, conditional under-18 parental consent engine, studio passport photograph guidance, and bKash 10-character uppercase Transaction ID formatter per FORM-02, FORM-03, FORM-04, DEC-05, DEC-09, and FIELD-DICTIONARY.md.

## Performance & Execution Overview

- **Tasks Completed:** 2 / 2
- **Test Suite Status:** 189 tests (1173 assertions) passing across the full project test suite; 15 tests (150 assertions) across Plan 05-03 test suites.
- **Commits:** 2 atomic feature commits.

## Accomplishments by Task

### Task 1: Public Membership Application Livewire Component and Clean Public Layout
- Created dedicated public layout `resources/views/forms/public-layout.blade.php`:
  - Fully decoupled from administrative CRM navigation (`layouts/admin.blade.php`).
  - Zero administrative navigation, internal links, or session leaks.
  - Features official American Spaces Bangladesh / EMK Center branding, U.S. Embassy partnership indicator, 256-bit TLS security badge, and full footer contact guidelines.
  - Loads Vite assets, `@livewireStyles`, and `@livewireScripts`.
- Created Livewire component `app/Livewire/Forms/PublicMembershipForm.php`:
  - Supports all 31 fields defined in `FIELD-DICTIONARY.md` Section 3 across 7 sections:
    1. Personal Information (Fields 1-6)
    2. Minor Applicant Consent (Field 7)
    3. Address in Bangladesh (Fields 8-13)
    4. Contact & Online Presence (Fields 14-16)
    5. Education & Professional Background (Fields 17-27)
    6. Identity Documents & Studio Photograph (Fields 28-29)
    7. Payment Verification & Terms Acceptance (Fields 30-31)
  - Mounts form by slug (defaults to `'membership-application'`), asserting publication status with HTTP 404 guard for draft/missing forms.
  - Supports JSON response for headless API inspections.
  - Implements real-time validation via `updated($propertyName)` and dedicated hook methods.
- Built responsive Blade template `resources/views/livewire/forms/public-membership-form.blade.php`:
  - Studio passport photo guidance: solid plain white background, no eyeglasses/sunglasses, both ears visible, regular attire, strictly no selfies, min 300x300px, max 4MB.
  - Identity document guidance: NID, Passport, Birth Certificate, Student ID (1-2 files).
  - bKash merchant payment guidance: account number, 10-character TxID requirement.
  - Submission confirmation screen displaying reference UUID and review instructions.
- Generated official template PDF at `public/forms/templates/EMK-Parental-Consent-Form.pdf` for applicant download.
- Registered public routes in `routes/web.php`:
  - `GET /apply` -> redirects to `/apply/membership-application`.
  - `GET /apply/{slug}` -> mounts `PublicMembershipForm`.
- Created feature tests in `tests/Feature/PublicMembershipFormRenderTest.php`:
  - Verified `/apply` redirect.
  - Verified 200 OK and public layout branding.
  - Verified complete absence of administrative shell links and console navigation.
  - Verified presence of all 31 fields, field labels, guidance text, and photo specification cards.
  - Verified HTTP 404 for draft and missing forms.
- **Commit:** `1875e47`

### Task 2: Reactive Cascading Geographic Hierarchy and Conditional Minor Consent Engine
- Enhanced `PublicMembershipForm.php` and `public-membership-form.blade.php` with reactive capabilities:
  - **Geographic Division & District Cascade (Fields 12 & 13):**
    - Selecting division dynamically repopulates `$available_districts` from `FormSchemaEngine::DIVISION_DISTRICT_MAP` across all 8 divisions and 64 districts (Barishal: 6, Chattogram: 11, Dhaka: 13, Khulna: 10, Mymensingh: 4, Rajshahi: 8, Rangpur: 8, Sylhet: 4).
    - If the current district does not belong to the newly selected division, it is automatically reset to null.
  - **Dynamic Minor Consent Trigger (Fields 6 & 7 per DEC-09):**
    - Date of birth input uses `wire:model.live.debounce.300ms="date_of_birth"`.
    - Server-side age calculation derives applicant age via `Carbon::parse($this->date_of_birth)->age`.
    - Client-side Alpine component calculates age in real time.
    - When age < 18: Field 7 transitions to visible with amber warning notice, template download link (`/forms/templates/EMK-Parental-Consent-Form.pdf`), and required upload validation.
    - When age >= 18: Field 7 is hidden, and `parental_consent_file` is cleared/nullified.
  - **Conditional Inputs:**
    - Occupation `'other'` toggles child input `occupation_other` with required validation.
    - Highest certification `'other'` toggles child input `highest_certification_other` with required validation.
    - U.S. work/study experience toggles child input `us_experience_location` with required validation.
    - American institution alumni toggles child input `american_institution_name` with required validation.
  - **bKash Transaction ID Formatting (Field 30 per DEC-05):**
    - Client Alpine input transform auto-uppercases and restricts input: `x-on:input="$el.value = $el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 10)"`.
    - Server-side validation strictly enforces exactly 10 uppercase alphanumeric characters (`size:10`, `regex:/^[A-Z0-9]{10}$/`).
- Created comprehensive feature tests in `tests/Feature/PublicFormReactivityTest.php`:
  - Verified division changes repopulate all 8 divisions with exact district counts.
  - Verified invalid districts reset to null on division change.
  - Verified age 16 and 17 set `requires_consent = true`.
  - Verified age 18, 25, and 40 set `requires_consent = false` and clear consent file.
  - Verified conditional validation for occupation, certification, US experience, and alumni.
  - Verified bKash TxID passes on valid 10-char uppercase string and fails on 9-char, 11-char, lowercase, or special characters.
- Ran full test suite: 189 tests passing with 1173 assertions.
- **Commit:** `468702c`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Blade @json directive argument parsing with array syntax**
- **Found during:** Task 1 verification with `FormTracerTest`
- **Issue:** Using `@json(in_array((string)$has_us_experience, ['1', 'true'], true))` inside an HTML attribute caused Blade's compiler to treat internal commas as directive parameter separators, resulting in a PHP ParseError syntax error.
- **Fix:** Simplified Alpine `x-show` expressions to evaluate root entangled Alpine properties directly (`x-show="hasUsExp == '1'"`, `x-show="isAlumni == '1'"`), removing extraneous `@json()` calls.
- **Files modified:** `resources/views/livewire/forms/public-membership-form.blade.php`
- **Commit:** `1875e47`

## Self-Check: PASSED

1. Created files exist:
   - `app/Livewire/Forms/PublicMembershipForm.php` (FOUND)
   - `resources/views/forms/public-layout.blade.php` (FOUND)
   - `resources/views/livewire/forms/public-membership-form.blade.php` (FOUND)
   - `public/forms/templates/EMK-Parental-Consent-Form.pdf` (FOUND)
   - `tests/Feature/PublicMembershipFormRenderTest.php` (FOUND)
   - `tests/Feature/PublicFormReactivityTest.php` (FOUND)

2. Commits exist in git history:
   - `1875e47` (FOUND)
   - `468702c` (FOUND)
