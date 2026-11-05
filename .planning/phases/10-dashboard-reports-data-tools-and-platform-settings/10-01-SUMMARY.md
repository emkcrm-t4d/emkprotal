---
phase: 10-dashboard-reports-data-tools-and-platform-settings
plan: "01"
subsystem: reports
tags:
  - operational-metrics
  - livewire
  - tenancy-defense
  - analytics
  - caching
dependency_graph:
  requires:
    - 04-identity-access-control-space-scope-and-audit-core
    - 06-leads-verification-and-controlled-conversion
    - 07-member-profiles-invoices-and-id-cards
  provides:
    - workspace-scoped-operational-metrics
    - operational-dashboard-component
    - multi-workspace-global-aggregation
  affects:
    - routes/web.php
tech_stack:
  added: []
  patterns:
    - data-transfer-object
    - aggregation-service
    - deterministic-cache-keying
    - tenancy-hydration-guard
key_files:
  created:
    - app/Domain/Reports/DTOs/OperationalMetricsDTO.php
    - app/Domain/Reports/Services/MetricsAggregationService.php
    - app/Livewire/Reports/OperationalDashboard.php
    - resources/views/livewire/reports/operational-dashboard.blade.php
    - tests/Feature/OperationalDashboardTest.php
    - tests/Feature/OperationalDashboardTenancyTest.php
  modified:
    - routes/web.php
decisions:
  - "Scoped operational queries directly using database-level selectRaw aggregations with 300s cache TTL."
  - "Enforced zero-division safety on conversionRate with 0.0% fallback when total leads equal zero."
  - "Guarded Livewire scope switching and global view behind superadmin/global_admin checks on mount, hydrate, and updating lifecycle hooks."
metrics:
  duration: 15 min
  completed_date: "2026-11-05"
status: complete
actuals:
  tokens: 22235
  tasks: 2
  commits: 2
  plan_head_before: "442b2c2097df4bb100bd30ad090510e82bd963a6"
  plan_head_after: "9243e07ca0a74e97259d5be968b9b994b9774dd5"
---

# Phase 10 Plan 01: Operational Metrics Aggregator and Dashboard Summary

**Workspace-scoped operational metrics aggregator, typed DTO, reactive Livewire dashboard with date range filters, 5-minute caching, and Superadmin cross-workspace consolidated view.**

---

## Executive Summary

Phase 10 Plan 01 delivered the core operational metrics infrastructure and reactive executive dashboard for the American Spaces CRM per **DASH-01**, **DASH-02**, and **DEC-01 (D-01)**.

Center managers can now view real-time workspace KPIs: Active Members, Pipeline Leads Under Review, Cards Ready for Pickup, and Total Collected Revenue in BDT, alongside a Lead Pipeline Funnel visualizer, member status distribution breakdown, and PVC card issuance lifecycle monitoring.

Superadmin users are equipped with a global multi-workspace consolidated view across Dhaka, Chattogram, Rajshahi, Sylhet, Barishal, and the American Center, while non-superadmin workspace managers are strictly isolated to their assigned space with defense-in-depth across route middleware, component hydration, and property update hooks.

---

## Tasks Completed

| Task | Name | Commit | Key Files |
| --- | --- | --- | --- |
| 1 | End-to-End Operational Metrics Aggregator, Workspace Scope Filter, & Dashboard Tracer Slice | `ef3b808` | `app/Domain/Reports/DTOs/OperationalMetricsDTO.php`<br>`app/Domain/Reports/Services/MetricsAggregationService.php`<br>`app/Livewire/Reports/OperationalDashboard.php`<br>`resources/views/livewire/reports/operational-dashboard.blade.php`<br>`routes/web.php`<br>`tests/Feature/OperationalDashboardTest.php` |
| 2 | Cross-Workspace Tenancy Defense & Superadmin Global Multi-Workspace Aggregation Toggle | `9243e07` | `app/Livewire/Reports/OperationalDashboard.php`<br>`resources/views/livewire/reports/operational-dashboard.blade.php`<br>`tests/Feature/OperationalDashboardTenancyTest.php` |

---

## Architectural & Security Highlights

1. **Typed Representation & Zero-Division Safety (`OperationalMetricsDTO`):**
   - Encapsulates integer counts for member statuses (`active`, `inactive`, `restricted`, `revoked`, `archived_reversed`), lead pipeline stages (`draft`, `submitted`, `under_review`, `missing_items`, `approved`, `converted`, `rejected`, `blocked`), card issuance states, and financial revenue.
   - Guarded conversion rate calculation: returns `0.0%` mathematically when `totalLeads == 0`, avoiding PHP `DivisionByZeroError`.

2. **Database-Level Aggregation & Deterministic Caching (`MetricsAggregationService`):**
   - Replaced N+1 query patterns with `selectRaw('status, count(*) as count')->groupBy('status')` scoped strictly to `workspace_id`.
   - Generates deterministic cache keys (`metrics_{$workspaceId}_{$dateFrom}_{$dateTo}`) with 300-second TTL.
   - Provides `clearMetricsCache(string $workspaceId)` to support instant dashboard re-computation upon manual refresh or record transitions.

3. **Multi-Workspace Tenancy Defense & Tampering Protection:**
   - Route protection: `VerifyWorkspaceAccess` middleware rejects cross-workspace navigation for non-superadmins with HTTP 403.
   - Component mount & hydration: `OperationalDashboard::hydrate()` and `updating*` lifecycle hooks intercept any Livewire payload tampering attempting to set `selectedWorkspaceScope` or `isGlobalView = true`.
   - Service barrier: `MetricsAggregationService::getGlobalMetrics()` asserts caller retains `superadmin` or `global_admin` role, throwing `TenancyBoundaryException` otherwise.

4. **Executive UI Presentation:**
   - 4-column responsive KPI cards matching the American Spaces palette.
   - Dynamic Lead Pipeline Funnel visualizer with proportional progress bars.
   - Date range controls supporting 7d, 30d, 90d, 1y, all-time presets, and custom range inputs.
   - Superadmin toggle with National American Spaces comparative overview table.

---

## Verification & Test Results

```
PASS  Tests\Feature\OperationalDashboardTest
✓ unauthenticated request is redirected
✓ staff without dashboard permission receives 403
✓ workspace manager can access dashboard and aliases
✓ dashboard displays accurate metrics and status breakdowns
✓ conversion rate safely handles zero leads without division by zero
✓ date presets recalculate metrics accurately
✓ metrics caching and refresh invalidation

PASS  Tests\Feature\OperationalDashboardTenancyTest
✓ workspace manager cannot access other workspace reports route
✓ workspace manager cannot toggle global view
✓ workspace manager cannot switch workspace scope
✓ workspace manager cannot tamper selected workspace scope on hydration
✓ workspace manager cannot tamper is global view on hydration
✓ unauthorized user invoking get global metrics throws exception
✓ superadmin can access any workspace dashboard and toggle global view
✓ superadmin can switch workspace scope
✓ global metrics accurately aggregates across multiple workspaces without data corruption

Tests: 16 passed (71 assertions)
Duration: 3.45s
```

---

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Foreign key and non-nullable test fixtures in SQLite**
- **Found during:** Task 1 & Task 2 test suite creation
- **Issue:** SQLite in-memory test database enforced `NOT NULL` on `leads.submitted_at` and `invoices.paid_at`, and `UNIQUE` on `users.email` when creating duplicate test managers.
- **Fix:** Provided explicit `submitted_at` timestamps in test lead factories, assigned valid `paid_at` timestamps in invoice factories, and retrieved seeded user references to prevent duplicate email insertions.
- **Files modified:** `tests/Feature/OperationalDashboardTest.php`, `tests/Feature/OperationalDashboardTenancyTest.php`.

---

## Self-Check: PASSED

1. Created files exist:
   - `app/Domain/Reports/DTOs/OperationalMetricsDTO.php`: FOUND
   - `app/Domain/Reports/Services/MetricsAggregationService.php`: FOUND
   - `app/Livewire/Reports/OperationalDashboard.php`: FOUND
   - `resources/views/livewire/reports/operational-dashboard.blade.php`: FOUND
   - `tests/Feature/OperationalDashboardTest.php`: FOUND
   - `tests/Feature/OperationalDashboardTenancyTest.php`: FOUND

2. Commits exist:
   - `ef3b808`: FOUND
   - `9243e07`: FOUND
