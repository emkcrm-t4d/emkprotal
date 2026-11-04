---
phase: 06-leads-verification-and-controlled-conversion
plan: 01
subsystem: leads
tags: [leads, directory, profile, tri-state, tenancy, livewire, tailwind, reapplication, audit]

requires:
  - phase: 05-form-builder-and-public-membership-form
    provides: Inbound form submissions generating Lead records with attachments and initial tri-state flags
  - phase: 02-architecture-data-model-and-security-design
    provides: Tenancy boundary contracts, TenantScope, BelongsToTenant, and ValidatesWorkspaceOnHydration

provides:
  - Livewire LeadDirectory component with multi-facet reactive filtering, debounced search, tri-state badges, and sorting
  - Livewire LeadProfile component with 31-field inspection grid, secure attachment streaming, and reapplicant history banner
  - Workspace isolation via middleware priority and Layer 2 validation
  - Editable contact details with audit trail logging

affects:
  - 06-02-PLAN.md
  - 06-03-PLAN.md
  - 06-04-PLAN.md
  - 06-05-PLAN.md
  - 06-06-PLAN.md

actuals:
  tokens: 35000
  tasks: 3
  commits: 3
  plan_head_before: 710bb98d437721cd694ad2d8434e3ac6d1350afe
  plan_head_after: bef58a23e958b104b10b2488d5a0e7bd1e5205dd

tech-stack:
  added: []
  patterns:
    - "Workspace authorization priority: VerifyWorkspaceAccess placed before SubstituteBindings in bootstrap/app.php to prevent 404 bypass"
    - "Layer 2 Livewire hydration defense via ValidatesWorkspaceOnHydration trait"
    - "Reactive query state with Livewire #[Url] attributes and resetPage hooks"
    - "Reapplication history audit by applicant email with prior rejection details banner"

key-files:
  created:
    - app/Domain/Leads/Models/LeadVerification.php
    - app/Livewire/Leads/LeadDirectory.php
    - resources/views/livewire/leads/lead-directory.blade.php
    - app/Livewire/Leads/LeadProfile.php
    - resources/views/livewire/leads/lead-profile.blade.php
    - tests/Feature/LeadTracerTest.php
    - tests/Feature/LeadDirectoryTest.php
    - tests/Feature/LeadProfileTest.php
  modified:
    - app/Domain/Leads/Models/Lead.php
    - bootstrap/app.php
    - routes/web.php

key-decisions:
  - "Configured middleware priority in bootstrap/app.php to ensure VerifyWorkspaceAccess runs before SubstituteBindings, guaranteeing HTTP 403 instead of 404 on cross-workspace route attempts"
  - "Enforced immutability on core identity fields (first_name, last_name, date_of_birth, bkash_txid, email) in LeadProfile while allowing staff with leads.edit to update address and contact info with audit logging"
  - "Reapplication detection checks historical lead records by applicant email (excluding current lead id) and renders amber banner with previous rejection codes per LEAD-07"

patterns-established:
  - "Tri-state indicator badges (P, D, Ph) for payment, document, and photo streams"
  - "Tabbed profile layout with prominent previous application history alert"

requirements-completed:
  - LEAD-01
  - LEAD-07

coverage:
  - id: D1
    description: "Workspace staff can view leads directory scoped to their workspace with tenancy defense"
    requirement: LEAD-01
    verification:
      - kind: integration
        ref: "tests/Feature/LeadTracerTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "Reactive search, status filtering, tri-state stream filtering, and sorting in lead directory"
    requirement: LEAD-01
    verification:
      - kind: integration
        ref: "tests/Feature/LeadDirectoryTest.php"
        status: pass
    human_judgment: false
  - id: D3
    description: "31-field profile inspector with secure document links, reapplicant history banner, and contact editing"
    requirement: LEAD-07
    verification:
      - kind: integration
        ref: "tests/Feature/LeadProfileTest.php"
        status: pass
    human_judgment: false

duration: 30min
completed: 2026-11-04
status: complete
---

# Phase 06 Plan 01: Lead Directory, Profile Inspector, and Tenancy Tracer Slice Summary

**Full-featured reactive Lead Directory, 31-field Profile Inspector, and Tenancy Defense tracer slice with reapplicant history detection and editable contact details**

## Performance

- **Duration:** ~30 min
- **Started:** 2026-11-04T10:03:17Z
- **Completed:** 2026-11-04T10:33:00Z
- **Tasks:** 3
- **Files modified:** 11

## Accomplishments

- Established routes `/crm/{workspace}/leads` and `/crm/{workspace}/leads/{lead}` protected by workspace authorization and `can:leads.view` permission.
- Created `LeadDirectory` Livewire component and Blade view featuring multi-facet reactive filtering (lead status, payment/doc/photo tri-state streams, date ranges), debounced search across applicant name, email, contact number, and bKash TxID, color-coded badges, and 3-dot tri-state indicators.
- Created `LeadProfile` Livewire component and Blade view featuring complete 31-field demographic and educational detail inspection, secure document download links, reapplicant history banner detecting prior rejected applications by email with past rejection reasons (LEAD-07), and staff contact/address editing with audit trail recording (`lead_contact_updated`).
- Hardened cross-workspace tenancy defense via `bootstrap/app.php` middleware priority, guaranteeing HTTP 403 on mismatched workspace access rather than 404 model binding errors.
- Verified test suite across 16 feature and architecture tests (114 assertions) with 0 failures.

## Task Commits

Each task was committed atomically:

1. **Task 1: End-to-End Lead Directory, Profile Inspector, and Tenancy Tracer Slice** - `2651284` (feat)
2. **Task 2: Reactive Lead Directory with Multi-Facet Filtering, Status Badges, and Search** - `a1cbff3` (feat)
3. **Task 3: Comprehensive Lead Profile Inspector, Reapplicant History Detection, and Editable Contact Details** - `bef58a2` (feat)

## Files Created/Modified

- `app/Domain/Leads/Models/LeadVerification.php` - Eloquent model representing stream verification entries with relationships to Lead and verifier User.
- `app/Domain/Leads/Models/Lead.php` - Added `verifications()` relation and `getFullNameAttribute()`.
- `bootstrap/app.php` - Added middleware priority order ensuring `VerifyWorkspaceAccess` executes before `SubstituteBindings`.
- `routes/web.php` - Registered `crm.leads` and `crm.leads.show` routes under workspace-scoped group.
- `app/Livewire/Leads/LeadDirectory.php` - Reactive directory component with query parameters, search, filtering, and tenancy isolation.
- `resources/views/livewire/leads/lead-directory.blade.php` - Responsive directory view with Tailwind data table, search toolbar, status pills, and stream indicators.
- `app/Livewire/Leads/LeadProfile.php` - Comprehensive lead profile inspector, reapplication detector, and contact detail editor with audit logging.
- `resources/views/livewire/leads/lead-profile.blade.php` - Tabbed profile inspector rendering 31 fields, private attachments, verification states, and reapplication alerts.
- `tests/Feature/LeadTracerTest.php` - Integration tests verifying workspace scoping, route access permissions, and HTTP 403 cross-workspace rejection.
- `tests/Feature/LeadDirectoryTest.php` - Feature tests verifying reactive search, status filters, stream filters, date ranges, and sorting.
- `tests/Feature/LeadProfileTest.php` - Feature tests verifying 31-field rendering, reapplication history alerts, editable contact saving, authorization enforcement, and core identity immutability.

## Decisions Made

- Configured middleware priority in `bootstrap/app.php` so that `VerifyWorkspaceAccess` executes before `SubstituteBindings`. This ensures any cross-workspace URL access halts immediately with HTTP 403 rather than triggering `ModelNotFoundException` under `TenantScope`.
- Maintained immutability on core identity fields (`first_name`, `last_name`, `date_of_birth`, `bkash_txid`, `email`) in `LeadProfile::saveContactDetails()`, allowing updates only to contact and address fields (`contact_number`, house/road/area/zip/division/district, occupation, and place of study) accompanied by audit log entries.
- Reapplication detection executes an email-based lookup for past leads within the workspace (`email == current, id != current`) and formats past verification attempts, rejection codes, and rejection notes in a prominent amber banner.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Middleware priority configuration for tenancy defense**
- **Found during:** Task 1 (End-to-End Tracer Slice)
- **Issue:** In Laravel, `SubstituteBindings` runs early by default. Cross-workspace route requests triggered `ModelNotFoundException` (HTTP 404) via `TenantScope` before `VerifyWorkspaceAccess` could evaluate the route workspace parameter against the user's workspace, failing the DEC-01 403 contract.
- **Fix:** Configured middleware priority order in `bootstrap/app.php` via `$middleware->priority(...)` placing `VerifyWorkspaceAccess` ahead of `SubstituteBindings`.
- **Files modified:** `bootstrap/app.php`
- **Verification:** `tests/Feature/LeadTracerTest.php#test_cross_workspace_route_access_is_blocked_with_403` passed.
- **Committed in:** `2651284`

**2. [Rule 2 - Missing Critical] Added LeadVerification domain model**
- **Found during:** Task 1 / Task 3
- **Issue:** The schema for `lead_verifications` existed from Phase 2 migrations, but the Eloquent model `LeadVerification` had not been defined. The directory and profile views require querying verification records for tri-state statuses and reapplication rejection details.
- **Fix:** Created `App\Domain\Leads\Models\LeadVerification` with relationships, casts, and added `verifications()` `hasMany` relation on `Lead`.
- **Files modified:** `app/Domain/Leads/Models/LeadVerification.php`, `app/Domain/Leads/Models/Lead.php`
- **Verification:** Relations and tests passed.
- **Committed in:** `2651284`

**3. [Rule 1 - Bug] Eager loading verifications on reapplication history check**
- **Found during:** Task 3 (Comprehensive Lead Profile Inspector)
- **Issue:** `LeadProfile` queried reapplication history with `Lead::where('email', ...)->get()` without eager-loading `verifications`, resulting in empty verification arrays and missing rejection codes in the banner.
- **Fix:** Changed query to `Lead::with('verifications')->where('email', $this->lead->email)->where('id', '!=', $this->lead->id)->get()`.
- **Files modified:** `app/Livewire/Leads/LeadProfile.php`
- **Verification:** `tests/Feature/LeadProfileTest.php#test_reapplication_history_banner_appears_when_prior_rejected_lead_exists` passed.
- **Committed in:** `bef58a2`

---

**Total deviations:** 3 auto-fixed (2 bugs, 1 missing critical model)
**Impact on plan:** All fixes were essential for correctness, tenancy defense, and requirement compliance. No scope creep.

## Issues Encountered

- In Blade template, Tailwind CSS class `uppercase` on `highest_certification` transformed text visually in browser but preserved lowercase text in HTML source. Resolved by adding `strtoupper(...)` in Blade output to ensure consistent string rendering and test assertion match.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Plan 06-01 completed with all foundational Lead Directory and Profile infrastructure active and tested.
- Ready for Plan 06-02: Tri-State Verification Action Handlers & Streams (Payment bKash TxID verification, Identity Document review, Studio Photo check, and Parental Consent handling).
- No blockers.

## Self-Check: PASSED

- All 8 created files exist on disk: FOUND
- All 3 commit hashes (`2651284`, `a1cbff3`, `bef58a2`) exist in git history: FOUND
- Test suites pass with 16 passed tests and 114 assertions: FOUND

---
*Phase: 06-leads-verification-and-controlled-conversion*
*Completed: 2026-11-04*
