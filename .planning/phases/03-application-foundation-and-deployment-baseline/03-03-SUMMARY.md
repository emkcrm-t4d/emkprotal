---
phase: 03-application-foundation-and-deployment-baseline
plan: 03
subsystem: platform-registry
tags:
  - registry
  - modules
  - actions
  - zero-eval
  - livewire
  - admin-shell
  - pest
requires:
  - 03-02
provides:
  - Central static ModuleRegistry singleton cataloging 8 core modules
  - Declared actions catalog with Spatie permission tokens
  - Hardcoded non-delegable locks on 16 managerial actions per T-01-07
  - EnsureModuleEnabled middleware enforcing 404 responses for disabled modules
  - Pest Architecture test suite enforcing zero dynamic code evaluation and domain boundaries
  - Responsive authenticated admin shell layout and reactive Livewire component
affects:
  - 03-04-PLAN.md
  - Phase 04 Identity & RBAC
  - Phase 05 Lead Ingestion & Public Forms
  - Phase 06 Tri-State Lead Verification
tech-stack:
  added: []
  patterns:
    - Static compile-time module and action registry eliminating runtime eval risks
    - AST-level Pest architecture enforcement for zero dynamic code evaluation
    - Decoupled workspace module enablement without permission row manipulation
    - Responsive administrative shell with Livewire 3 and Alpine.js micro-interactions
key-files:
  created:
    - app/Domain/Platform/Registry/ModuleRegistryInterface.php
    - app/Domain/Platform/Registry/ModuleRegistry.php
    - app/Domain/Platform/Registry/ModuleDefinition.php
    - app/Domain/Platform/Registry/ActionDefinition.php
    - app/Http/Middleware/EnsureModuleEnabled.php
    - app/Providers/ModuleRegistryServiceProvider.php
    - tests/Arch/ModuleRegistryArchTest.php
    - tests/Feature/ModuleRegistryTest.php
    - tests/Feature/AdminShellTest.php
    - app/Livewire/Admin/AdminShell.php
    - resources/views/layouts/admin.blade.php
    - resources/views/livewire/admin/admin-shell.blade.php
  modified:
    - bootstrap/providers.php
decisions:
  - "[Plan 03-03]: Enforce zero dynamic code evaluation invariant across App namespace via Pest Arch tests per DEC-02 (D-02)"
  - "[Plan 03-03]: Hardcode 16 strictly non-delegable managerial actions in ModuleRegistry contract per T-01-07"
  - "[Plan 03-03]: Intercept disabled workspace modular routes with HTTP 404 in EnsureModuleEnabled middleware per MODULE-REGISTRY.md"
  - "[Plan 03-03]: Provide baseline responsive admin shell with workspace tenancy header (ws-emk-dhaka) and active module navigation per FOUN-04"
status: complete
actuals:
  tokens: 11797
  tasks: 3
  commits: 3
  plan_head_before: e3b3cf1c1be6079c49a8f3818344b0aa328e1805
  plan_head_after: 11b366d3f3a19fdeabc2b0a3232d24ca51e4a441
---

# Phase 3 Plan 03: Central Static ModuleRegistry, Zero-Eval Architecture Tests, and Authenticated Admin Shell Summary

## Overview

Plan 03-03 implemented the compile-time modular architecture, anti-eval security guards, and baseline administrative UI shell for the EMK Membership CRM monolith within Laravel 12 in accordance with **FOUN-04**, **DEC-01 (D-01)**, **DEC-02 (D-02)**, **T-01-07**, and **MODULE-REGISTRY.md**.

The central static `ModuleRegistry` catalogs all 8 core modules and declared actions without any runtime eval or dynamic plugin loading, formally locks the 16 managerial non-delegable actions to eliminate privilege escalation risks, and provides decoupled workspace module enablement checks. Automated Pest Architecture tests enforce the Zero Dynamic Code Evaluation Invariant in CI, and the authenticated admin shell provides a responsive Livewire 3 foundation for all subsequent administrative interfaces.

## Key Changes by Task

### Task 1: ModuleRegistry Singleton, Declared Actions Catalog, and Enablement Middleware
- **Domain Registry Contracts & Value Objects:**
  - `app/Domain/Platform/Registry/ModuleRegistryInterface.php`: Authoritative contract declaring `getModules()`, `getModule()`, `hasModule()`, `getDeclaredActions()`, `isActionRegistered()`, `isDelegable()`, `isStrictlyNonDelegable()`, and `isModuleEnabledForWorkspace()`.
  - `app/Domain/Platform/Registry/ModuleDefinition.php`: Readonly value object encapsulating module key, human name, domain namespace, description, and registered action objects.
  - `app/Domain/Platform/Registry/ActionDefinition.php`: Readonly value object encapsulating Spatie action token, module key, display name, description, delegable flag, and non-delegable managerial flag.
- **Static ModuleRegistry Implementation (`app/Domain/Platform/Registry/ModuleRegistry.php`):**
  - Statically compiles all 8 core modules: `forms`, `leads`, `members`, `member_portal`, `invoices`, `dashboard_reports`, `platform_services`, and `migration`.
  - Registers the complete catalog of declared actions with Spatie syntax `{module}.{action}`.
  - Formally locks the **16 Strictly Non-Delegable Managerial Actions** per **T-01-07**:
    - `leads.approve`, `leads.reject`, `leads.convert`, `leads.reverse_conversion`, `leads.verify_payment`, `leads.delete_soft`
    - `forms.publish`, `forms.archive`
    - `invoices.generate`, `invoices.verify_payment`
    - `platform_services.manage_settings`, `platform_services.manage_users`, `platform_services.delegate_roles`, `platform_services.view_audit_logs`
    - `members.restrict`, `members.revoke`
  - Defines the 8 Delegable Operational Actions: `forms.create`, `forms.edit`, `members.edit`, `members.export_csv`, `members.export_excel`, `invoices.export`, `dashboard_reports.generate_reports`, and `dashboard_reports.export_metrics`.
  - Implements fail-closed workspace module enablement checking with support for testing overrides and workspace configuration.
- **Enablement Middleware (`app/Http/Middleware/EnsureModuleEnabled.php`):**
  - Resolves workspace tenancy from route parameters or request context, verifies module enablement via `ModuleRegistryInterface`, and aborts with HTTP 404 (`Module [moduleKey] is not enabled for this workspace.`) when disabled.
- **Service Container Singleton (`app/Providers/ModuleRegistryServiceProvider.php`):**
  - Binds `ModuleRegistryInterface` as an immutable singleton in the container.
  - Registered in `bootstrap/providers.php`.
- Committed atomically in `cf42c0e`.

### Task 2: Zero-Eval Pest Architecture Tests and Registry Feature Test Suite
- **Architecture Invariant Enforcement (`tests/Arch/ModuleRegistryArchTest.php`):**
  - Asserts that `eval()` and `create_function()` are strictly banned across the application (`not->toBeUsed()`).
  - Asserts that `App\Domain` classes strictly depend only on `Illuminate`, `App\Domain`, `Spatie\Permission`, `Carbon\Carbon`, and `Ramsey\Uuid`.
  - Asserts that controllers and Livewire components do not use dynamic evaluation constructs.
- **Feature Test Suite (`tests/Feature/ModuleRegistryTest.php`):**
  - Verified `ModuleRegistry::getModules()` returns exactly 8 registered core modules with correct namespaces.
  - Verified `ModuleRegistry::getDeclaredActions()` catalogs all declared actions with valid tokens and module references.
  - Verified `isActionRegistered()` returns true for valid action tokens and false for unknown tokens.
  - Verified `isStrictlyNonDelegable()` returns true for all 16 managerial actions and false for delegable actions.
  - Verified `isDelegable()` returns true for the 8 delegable actions allowlist.
  - Verified `EnsureModuleEnabled` middleware allows active modules and aborts with HTTP 404 for disabled or unregistered modules.
- Committed atomically in `f7f6c70`.

### Task 3: Authenticated Admin Shell Layout and Reactive Livewire Component
- **HTML5 Responsive Admin Layout (`resources/views/layouts/admin.blade.php`):**
  - Provides responsive viewport, `csrf-token` meta tag, Vite asset bundle directives (`resources/css/app.css`, `resources/js/app.js`), `@livewireStyles`, and `@livewireScripts`.
  - Includes notification and toast alert slot container for status and error feedback.
  - Renders `<livewire:admin.admin-shell>` and canvas area supporting both `$slot` and `@yield('content')`.
- **Livewire Admin Shell (`app/Livewire/Admin/AdminShell.php` & `resources/views/livewire/admin/admin-shell.blade.php`):**
  - **Tenancy Workspace Header:** Displays active workspace name (`EMK Center Dhaka`), workspace identifier (`ws-emk-dhaka`), and active indicator badge (`● Active Tenant`) per **DEC-01 (D-01)**.
  - **Navigation Sidebar:** Queries `ModuleRegistryInterface`, iterates through enabled core modules, highlights active module, and omits disabled modules.
  - **Staff User Session Widget:** Displays authenticated user name, email, guard indicator (`guard: web`), role badge (`Workspace Staff`), and secure logout form per **DEC-06 (D-06)**.
  - **Alpine.js Micro-Interactions:** Responsive drawer with backdrop for mobile viewports (`x-data="{ mobileMenuOpen: false }"`).
- **Admin Shell Feature Test (`tests/Feature/AdminShellTest.php`):**
  - Tested Livewire component rendering of workspace header, guard badge, and core module navigation links.
  - Tested layout template rendering with slot content and CSRF tokens.
- Committed atomically in `11b366d`.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Replaced global `config()` call in ModuleRegistry with facade to satisfy Pest Arch test**
- **Found during:** Task 2 architecture testing
- **Issue:** Running `tests/Arch/ModuleRegistryArchTest.php` failed because `ModuleRegistry.php` called the global helper function `config()`, violating the domain encapsulation rule that `App\Domain` only uses `Illuminate`, `App\Domain`, etc.
- **Fix:** Replaced `config(...)` with `\Illuminate\Support\Facades\Config::get(...)`.
- **Files modified:** `app/Domain/Platform/Registry/ModuleRegistry.php`
- **Commit:** `f7f6c70`

**2. [Rule 1 - Bug] Added request attribute fallback for workspace parameter in EnsureModuleEnabled**
- **Found during:** Task 2 middleware feature testing
- **Issue:** Manual request instances in unit tests do not possess a routed `Route` object, causing `$request->route('workspace')` to return null.
- **Fix:** Updated `EnsureModuleEnabled::handle()` to check `$request->route('workspace') ?? $request->attributes->get('workspace')`.
- **Files modified:** `app/Http/Middleware/EnsureModuleEnabled.php`
- **Commit:** `f7f6c70`

**3. [Rule 2 - Critical Functionality] Guarded logout route helper in admin shell view before Phase 4 Auth routes exist**
- **Found during:** Task 3 admin shell rendering test
- **Issue:** Direct call to `route('logout')` threw `RouteNotFoundException` because Phase 04 implements Auth routes.
- **Fix:** Added safe conditional check: `Route::has('logout') ? route('logout') : url('/logout')`.
- **Files modified:** `resources/views/livewire/admin/admin-shell.blade.php`
- **Commit:** `11b366d`

## Known Stubs

None. All interfaces, value objects, registry implementations, middleware, architecture tests, feature tests, and Blade/Livewire components are fully implemented and verified without stubs.

## Threat Flags

None. All threat model mitigations from PLAN.md were implemented and verified:
- `T-03-07`: Zero dynamic code evaluation enforced via `tests/Arch/ModuleRegistryArchTest.php`.
- `T-03-08`: 16 managerial non-delegable actions locked in `ModuleRegistry::STRICTLY_NON_DELEGABLE` per `T-01-07`.
- `T-03-09`: Disabled workspace module requests intercepted with HTTP 404 in `EnsureModuleEnabled`.

## Self-Check: PASSED

1. **Created files exist:**
   - `app/Domain/Platform/Registry/ModuleRegistryInterface.php`: FOUND
   - `app/Domain/Platform/Registry/ModuleRegistry.php`: FOUND
   - `app/Domain/Platform/Registry/ModuleDefinition.php`: FOUND
   - `app/Domain/Platform/Registry/ActionDefinition.php`: FOUND
   - `app/Http/Middleware/EnsureModuleEnabled.php`: FOUND
   - `app/Providers/ModuleRegistryServiceProvider.php`: FOUND
   - `tests/Arch/ModuleRegistryArchTest.php`: FOUND
   - `tests/Feature/ModuleRegistryTest.php`: FOUND
   - `tests/Feature/AdminShellTest.php`: FOUND
   - `app/Livewire/Admin/AdminShell.php`: FOUND
   - `resources/views/layouts/admin.blade.php`: FOUND
   - `resources/views/livewire/admin/admin-shell.blade.php`: FOUND

2. **Commits exist in Git history:**
   - `cf42c0e`: FOUND
   - `f7f6c70`: FOUND
   - `11b366d`: FOUND
