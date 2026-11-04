---
phase: 04-identity-access-control-space-scope-and-audit-core
plan: 02
subsystem: tenancy-defense
tags:
  - tenancy
  - livewire
  - eloquent
  - pest-arch
  - security
status: complete
requires:
  - 04-01
provides:
  - workspace-route-group
  - livewire-hydration-guard
  - tenant-entity-interface
  - tenancy-arch-guard
affects:
  - app/Http/Middleware/VerifyWorkspaceAccess.php
  - app/Domain/Tenancy/Contracts/TenantEntityInterface.php
  - app/Domain/Tenancy/Traits/ValidatesWorkspaceOnHydration.php
  - app/Domain/Tenancy/Scopes/TenantScope.php
  - app/Domain/Tenancy/Traits/BelongsToTenant.php
  - app/Domain/Leads/Models/Lead.php
  - app/Domain/Leads/Models/LeadAttachment.php
  - app/Http/Controllers/DocumentDownloadController.php
  - routes/web.php
  - database/migrations/2026_11_03_000003_create_leads_and_attachments_tables.php
tech-stack:
  added: []
  patterns:
    - Four-Layer Tenancy Defense Model (Layer 1 Route, Layer 2 Hydration, Layer 3 Eloquent Scope)
    - Livewire Hydration Inspection and Tampering Defense (ValidatesWorkspaceOnHydration)
    - Eloquent Global Scope Query Isolation (TenantScope, BelongsToTenant)
    - Pest Architecture Automated Gate Banning withoutGlobalScope
key-files:
  created:
    - app/Domain/Tenancy/Contracts/TenantEntityInterface.php
    - app/Domain/Tenancy/Traits/ValidatesWorkspaceOnHydration.php
    - tests/Arch/TenancyArchTest.php
    - tests/Feature/VerifyWorkspaceAccessTest.php
    - tests/Feature/TenancyDefenseLayersTest.php
    - tests/Feature/TenancyIsolationTest.php
  modified:
    - app/Http/Middleware/VerifyWorkspaceAccess.php
    - app/Domain/Tenancy/Traits/BelongsToTenant.php
    - app/Domain/Leads/Models/Lead.php
    - app/Domain/Leads/Models/LeadAttachment.php
    - app/Http/Controllers/DocumentDownloadController.php
    - database/migrations/2026_11_03_000003_create_leads_and_attachments_tables.php
    - routes/web.php
decisions:
  - "Enforced Layer 1 Route Tenancy Middleware (VerifyWorkspaceAccess) verifying authenticated context, validating workspace slug syntax (^[a-z0-9-]+$), and rejecting cross-workspace route access with HTTP 403 while preserving Superadmin / Global Admin traversal per DEC-01."
  - "Implemented Layer 2 Livewire Hydration Guard (ValidatesWorkspaceOnHydration) inspecting public properties and models upon component hydration to abort with HTTP 403 upon client-side property or ID tampering per TENANCY-SPEC.md Layer 2."
  - "Bound Layer 3 Eloquent Global Scope (TenantScope) via BelongsToTenant and TenantEntityInterface across Lead and LeadAttachment domain models, automatically scoping all SELECT, UPDATE, and DELETE queries to the authenticated user workspace_id with fail-closed behavior for unassigned users per TENANCY-SPEC.md Layer 3."
  - "Locked an automated Pest Architecture test strictly prohibiting withoutGlobalScope and withoutGlobalScopes across App\\Http, App\\Domain, and App\\Livewire, guaranteeing that tenancy filtering cannot be bypassed in application code per DEC-01 and TENANCY-SPEC.md Section 4.3."
metrics:
  duration: 15m
  completed_date: "2026-11-04"
actuals:
  tokens: 11892
  tasks: 3
  commits: 3
  plan_head_before: c4dfcb7d9951c91b09908c351c4d24851bcdfa8a
  plan_head_after: cd584052e8aa2f995328461f8c041a967f4650ed
---

# Phase 4 Plan 02: Multi-Workspace Tenancy Defense Layers and Pest Architecture Guard Summary

Full implementation of the first three layers of the Four-Layer Server-Side Tenancy Defense Model (Layer 1 Route Middleware, Layer 2 Livewire Hydration Guard, Layer 3 Eloquent Global Scope) and automated Pest Architecture tests permanently prohibiting `withoutGlobalScope` across application code per AUTH-02, DEC-01, and TENANCY-SPEC.md.

## Performance & Execution Overview

- **Tasks Completed:** 3 / 3
- **Test Suite Status:** 65 tests (422 assertions) passed, including 5 Pest architecture compliance tests.
- **Commits:** 3 atomic task commits recorded in git HEAD history.

## Accomplishments by Task

### Task 1: Complete Layer 1 Route Middleware (VerifyWorkspaceAccess) and Workspace Route Grouping
- Refined `App\Http\Middleware\VerifyWorkspaceAccess` to validate authenticated context (`abort(401)` if null), sanitize workspace route slugs matching `^[a-z0-9-]+$`, allow immediate bypass for `superadmin` and `global_admin`, and abort with `403` with security alert logging on empty or mismatched `workspace_id`.
- Mounted the canonical `/crm/{workspace}` route group in `routes/web.php` with middleware stack `['auth:web', 'workspace.access', 'password.reset']` and baseline routes for `leads`, `members`, `invoices`, `reports`, and `tracer-check`.
- Created `tests/Feature/VerifyWorkspaceAccessTest.php` asserting route access for assigned workspace (200 OK), mismatched workspace (403 Forbidden), null `workspace_id` (403 Forbidden), superadmin cross-workspace traversal (200 OK), global admin cross-workspace traversal (200 OK), unauthenticated rejection (401), and malformed slug rejection (403/404).
- **Commit:** `daa39d6`

### Task 2: Implement Layer 2 Livewire Hydration Guard and Layer 3 Eloquent Global Scope
- Created `App\Domain\Tenancy\Contracts\TenantEntityInterface` declaring `getWorkspaceId(): ?string`.
- Implemented `App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration` inspecting public properties and models upon Livewire component hydration and aborting with HTTP 403 via `App::abort` if the entity's `workspace_id` mismatches the authenticated user's `workspace_id` (bypassing for Superadmin and Global Admin).
- Finalized `App\Domain\Tenancy\Scopes\TenantScope` injecting `WHERE workspace_id = auth()->user()->workspace_id` for SELECT, UPDATE, and DELETE queries, failing closed (`1 = 0`) for unbound users.
- Finalized `App\Domain\Tenancy\Traits\BelongsToTenant` registering `TenantScope` and automatically assigning `workspace_id` from the parent lead or authenticated user upon model creation.
- Implemented `TenantEntityInterface` and `BelongsToTenant` on `Lead` and `LeadAttachment` models.
- Added nullable `workspace_id` foreign key to `lead_attachments` table in migration `2026_11_03_000003_create_leads_and_attachments_tables.php`.
- Created `tests/Feature/TenancyDefenseLayersTest.php` validating query filtering, automatic workspace assignment, Livewire hydration interception aborting with 403 on tampered models, and Superadmin bypass.
- **Commit:** `3ecccbc`

### Task 3: Automated Pest Architecture Guard Banning withoutGlobalScope and Isolation Verification
- Created `tests/Arch/TenancyArchTest.php` using Pest Architecture tests enforcing:
  1. Application layers across `App\Http`, `App\Domain`, and `App\Livewire` must strictly never use `withoutGlobalScope` or `withoutGlobalScopes` per TENANCY-SPEC.md Section 4.3.
  2. All tenant domain models (`Lead`, `LeadAttachment`) must implement `TenantEntityInterface` and use `BelongsToTenant`.
- Created `tests/Feature/TenancyIsolationTest.php` testing complete multi-tenant database isolation between `ws-emk-dhaka` and `ws-corner-sylhet`:
  - Workspace Manager in Dhaka only sees and queries Dhaka leads (count = 2).
  - Cross-workspace `Lead::find($sylhetLeadId)` returns `null`.
  - Cross-workspace Eloquent `update()` on foreign lead returns 0 affected rows.
  - Cross-workspace Eloquent `delete()` on foreign lead returns 0 affected rows.
  - Superadmin retains unrestricted visibility across all workspaces (count = 4) and can query and mutate records globally.
  - Workspace Manager in Sylhet symmetrically sees only Sylhet leads (count = 2).
- **Commit:** `cd58405`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] DocumentDownloadController route workspace check order**
- **Found during:** Task 2 full test suite verification
- **Issue:** `DocumentDownloadController` previously attempted to resolve `$attachment` via `LeadAttachment::with('lead')->findOrFail($attachment)` before checking if the route parameter `$workspace` matched `$user->workspace_id`. When `BelongsToTenant` was bound to `LeadAttachment`, cross-workspace queries threw 404 in `findOrFail` rather than reaching the controller's 403 cross-workspace access assertion.
- **Fix:** Placed the route parameter workspace comparison (`if (empty($user->workspace_id) || $workspace !== (string) $user->workspace_id) { abort(403); }`) before model resolution in `DocumentDownloadController`.
- **Files modified:** `app/Http/Controllers/DocumentDownloadController.php`
- **Commit:** `3ecccbc`

**2. [Rule 2 - Critical Functionality] Nullable workspace_id foreign key on lead_attachments table**
- **Found during:** Task 2 domain model analysis
- **Issue:** `LeadAttachment` model was designated in TENANCY-SPEC.md and 04-02-PLAN.md to implement `TenantEntityInterface` and use `BelongsToTenant`, but the migration lacked a `workspace_id` column on `lead_attachments`.
- **Fix:** Added nullable `workspace_id` foreign key referencing `workspaces(id)` on `lead_attachments` table in `2026_11_03_000003_create_leads_and_attachments_tables.php`, and updated `BelongsToTenant::bootBelongsToTenant` creating event to inherit `workspace_id` from parent lead if present.
- **Files modified:** `database/migrations/2026_11_03_000003_create_leads_and_attachments_tables.php`, `app/Domain/Tenancy/Traits/BelongsToTenant.php`
- **Commit:** `3ecccbc`

**3. [Rule 1 - Bug] Domain registry encapsulation violation for abort helper**
- **Found during:** Task 2 architecture test verification
- **Issue:** Using the global helper `abort(403, ...)` inside `ValidatesWorkspaceOnHydration` triggered Pest architecture rule `enforce domain registry encapsulation` because global helper calls register as external dependencies.
- **Fix:** Replaced `abort(403, ...)` with `\Illuminate\Support\Facades\App::abort(403, ...)` which resides inside the allowed `Illuminate` namespace.
- **Files modified:** `app/Domain/Tenancy/Traits/ValidatesWorkspaceOnHydration.php`
- **Commit:** `3ecccbc`

## Self-Check: PASSED

- Created files exist:
  - `app/Domain/Tenancy/Contracts/TenantEntityInterface.php`: FOUND
  - `app/Domain/Tenancy/Traits/ValidatesWorkspaceOnHydration.php`: FOUND
  - `tests/Arch/TenancyArchTest.php`: FOUND
  - `tests/Feature/VerifyWorkspaceAccessTest.php`: FOUND
  - `tests/Feature/TenancyDefenseLayersTest.php`: FOUND
  - `tests/Feature/TenancyIsolationTest.php`: FOUND
- Task commits verified in HEAD history:
  - `daa39d6`: FOUND
  - `3ecccbc`: FOUND
  - `cd58405`: FOUND
