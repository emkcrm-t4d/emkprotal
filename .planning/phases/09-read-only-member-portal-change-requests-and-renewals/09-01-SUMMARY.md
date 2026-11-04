---
phase: 09-read-only-member-portal-change-requests-and-renewals
plan: 01
status: complete
subsystem: portal
tags: [member-portal, authentication, multi-guard, forced-password-reset, livewire, audit-trail]

requires:
  - phase: 04-identity-access-control-space-scope-and-audit-core
    provides: Dedicated 'member' auth guard, MemberUser model, and immutable AuditLog foundation
  - phase: 06-leads-verification-and-controlled-conversion
    provides: Member provisioning logic with initial must_reset_password flag
  - phase: 07-member-profiles-invoices-and-id-cards
    provides: Member domain model with member_id sequence identifier

provides:
  - MemberUser model enhanced with member() relationship
  - EnsureMemberPasswordReset middleware with fail-closed enforcement and redirect loop prevention
  - PortalLogin Livewire component supporting dual identifier resolution (Email or Member ID) and 5 attempt/min rate limiting
  - PortalFirstPasswordReset Livewire component enforcing permanent credential configuration on initial login
  - Clean responsive Tailwind portal shell layout in resources/views/layouts/portal.blade.php
  - Dedicated member portal authentication and profile routing in routes/web.php
  - Comprehensive feature tests in tests/Feature/PortalAuthenticationAndTracerTest.php

affects:
  - 09-02-PLAN (Read-Only Member Profile & Digital ID Card Display)
  - 09-03-PLAN (Self-Service Change Request Engine & Field Diff Auditing)
  - 09-04-PLAN (Membership Renewal Workflow & bKash Fee Submission)

actuals:
  tokens: 13437
  tasks: 2
  commits: 2
  plan_head_before: 48898bc45961974b8c35e3ebfb238009fd76009b
  plan_head_after: 5ad908574036663ee89f0cf619f0950009122116

tech-stack:
  added: []
  patterns:
    - "Multi-guard session isolation using dedicated 'member' guard and MemberUser provider"
    - "Dual-identifier login resolution dynamically mapping formatted Member IDs (EMK-YYYY-NNNNN) or emails to user credentials"
    - "Fail-closed forced password reset middleware with explicit route whitelist preventing infinite redirect loops"
    - "Immutable audit logging for authentication and password lifecycle changes (portal_member_login, portal_member_password_reset)"

key-files:
  created:
    - app/Http/Middleware/EnsureMemberPasswordReset.php
    - app/Livewire/Portal/PortalLogin.php
    - resources/views/livewire/portal/portal-login.blade.php
    - app/Livewire/Portal/PortalFirstPasswordReset.php
    - resources/views/livewire/portal/portal-first-password-reset.blade.php
    - resources/views/layouts/portal.blade.php
    - resources/views/portal/placeholder-profile.blade.php
    - tests/Feature/PortalAuthenticationAndTracerTest.php
  modified:
    - app/Models/MemberUser.php
    - bootstrap/app.php
    - routes/web.php

key-decisions:
  - "Decoupled PortalLogin from staff login routes, isolating session cookies and authorization rules under 'member' guard per PORT-01 and T-09-02"
  - "Whitelisted portal.password.reset, portal.password.update, and portal.logout in EnsureMemberPasswordReset middleware to eliminate infinite redirect loops per DEC-06 (D-06)"
  - "Emitted dedicated immutable AuditLog records with actor_guard='member' for both login and password updates per DEC-11 (D-11)"
---

# Phase 09 Plan 01: Member Portal Authentication, Dual-Identifier Login & Forced Password Reset Summary

End-to-end Member Portal authentication gateway, multi-guard session boundary, dual-identifier login resolver (Email or Member ID), and first-login forced password reset interceptor with redirect loop prevention per PORT-01, DEC-06 (D-06), and DEC-11 (D-11).

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | End-to-End Member Authentication, Dual-Identifier Login & Portal Entry Slice | `5fc6be1` | `app/Models/MemberUser.php`, `app/Livewire/Portal/PortalLogin.php`, `resources/views/livewire/portal/portal-login.blade.php`, `resources/views/layouts/portal.blade.php`, `routes/web.php`, `tests/Feature/PortalAuthenticationAndTracerTest.php` |
| 2 | Forced First-Login Password Reset Interceptor with Redirect Loop Prevention | `5ad9085` | `app/Http/Middleware/EnsureMemberPasswordReset.php`, `bootstrap/app.php`, `app/Livewire/Portal/PortalFirstPasswordReset.php`, `resources/views/livewire/portal/portal-first-password-reset.blade.php`, `routes/web.php`, `tests/Feature/PortalAuthenticationAndTracerTest.php` |

## Key Changes & Implementations

### 1. Dual-Identifier Authentication Gateway (`PortalLogin`)
- Public Livewire component at `/portal/login` supporting login via either email address or official Member ID (e.g. `EMK-2026-00001`).
- Dynamically resolves alphanumeric Member IDs against `Member::withoutGlobalScopes()->where('member_id', ...)->first()` to locate corresponding credentials.
- Protects against brute-force attacks by rate limiting requests to 5 attempts per minute per IP and identifier using Laravel `RateLimiter`.
- Disallows deactivated accounts (`is_active = false`) with clear error messaging.
- Validates credentials strictly against `Auth::guard('member')` and regenerates the session upon success.
- Emits immutable `portal_member_login` audit events to `audit_logs` table.

### 2. First-Login Password Reset Interceptor (`EnsureMemberPasswordReset`)
- Fail-closed middleware inspecting `must_reset_password` on the authenticated member user.
- Whitelists `portal.password.reset`, `portal.password.update`, and `portal.logout` to eliminate infinite redirect loops per DEC-06 (D-06) and T-09-03.
- Returns HTTP 403 Forbidden with `password_reset_required: true` for API/JSON calls and redirects web requests to `/portal/password/reset`.
- Forwards compliant members (`must_reset_password = false`) away from the reset screen back to `/portal/profile`.
- Aliased as `member.password.reset` in `bootstrap/app.php`.

### 3. Password Setup Component (`PortalFirstPasswordReset`)
- Verifies current temporary password against existing hashed password (or bypasses when provisioned with null password).
- Validates permanent password strength (minimum 8 characters, confirmed, different from temporary password).
- Updates password hash, sets `must_reset_password = false`, records immutable `portal_member_password_reset` audit log, and flashes welcome confirmation.

### 4. Portal Shell Layout (`layouts/portal.blade.php`)
- Modern responsive layout using Tailwind CSS with official EMK Center & American Spaces branding.
- Displays authenticated member name, formatted Member ID badge, CSRF-protected logout button, and dismissible status notification banners.

## Verification & Test Results

`php artisan test tests/Feature/PortalAuthenticationAndTracerTest.php` executed with 14 passed assertions (70 assertions, 100% pass):
- `test_login_screen_renders_successfully` (PASS)
- `test_authentication_succeeds_using_valid_email_and_password` (PASS)
- `test_authentication_succeeds_using_valid_member_id_and_password` (PASS)
- `test_authentication_fails_with_invalid_password_or_nonexistent_member_id` (PASS)
- `test_deactivated_member_user_cannot_log_in` (PASS)
- `test_staff_credentials_cannot_authenticate_under_member_portal` (PASS)
- `test_rate_limiting_throttles_excessive_attempts` (PASS)
- `test_portal_logout_clears_session` (PASS)
- `test_authenticated_member_with_must_reset_password_is_redirected_to_password_reset` (PASS)
- `test_json_request_when_password_reset_required_returns_forbidden` (PASS)
- `test_direct_get_to_password_reset_does_not_redirect_or_loop` (PASS)
- `test_submitting_valid_new_password_clears_flag_and_allows_profile_access` (PASS)
- `test_password_reset_validates_mismatch_and_short_passwords` (PASS)
- `test_compliant_member_visiting_password_reset_is_redirected_to_profile` (PASS)

Guard isolation verification (`MultiGuardAuthTest`):
- `tests/Feature/MultiGuardAuthTest.php` passed 5 tests (30 assertions).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Safe check for session store on Livewire request regeneration**
- **Found during:** Task 1 test verification
- **Issue:** `request()->session()->regenerate()` throws `RuntimeException: Session store not set on request` during headless Livewire component unit testing.
- **Fix:** Added `if (request()->hasSession()) { request()->session()->regenerate(); }` check.
- **Files modified:** `app/Livewire/Portal/PortalLogin.php`
- **Commit:** `5fc6be1`

**2. [Rule 1 - Bug] Schema completeness in member test fixtures**
- **Found during:** Task 1 test execution
- **Issue:** Test fixtures for `Lead`, `MembershipTerm`, and `Member` failed SQLite NOT NULL constraints for `membership_terms.code`, `leads.bkash_txid`, `leads.age_at_submission`, and required address/education columns.
- **Fix:** Updated test helper `createMemberFixture` with all schema-compliant columns.
- **Files modified:** `tests/Feature/PortalAuthenticationAndTracerTest.php`
- **Commit:** `5fc6be1`

## Known Stubs

- `resources/views/portal/placeholder-profile.blade.php`: Line 1 - Temporary profile dashboard landing placeholder. This placeholder view is intentional and will be replaced in Plan 09-02 by the fully featured read-only member profile view and digital ID card component.

## Threat Model Verification

- **T-09-01 (Spoofing):** RateLimiter throttles login attempts to 5 attempts per minute based on IP address and lowercase identifier. Verified via `test_rate_limiting_throttles_excessive_attempts`.
- **T-09-02 (Elevation of Privilege):** Authentication is strictly confined to `Auth::guard('member')` and `MemberUser`. Staff credentials under `web` guard cannot authenticate on `/portal/login` and member session cannot access CRM staff routes. Verified via `test_staff_credentials_cannot_authenticate_under_member_portal` and `MultiGuardAuthTest`.
- **T-09-03 (Security Bypass):** `EnsureMemberPasswordReset` enforces fail-closed interception of members with `must_reset_password = true`, with explicit loop-prevention exemptions on reset and logout routes. Verified via `test_authenticated_member_with_must_reset_password_is_redirected_to_password_reset` and `test_direct_get_to_password_reset_does_not_redirect_or_loop`.

## Self-Check: PASSED
- `app/Models/MemberUser.php` exists and contains `member()` relationship
- `app/Http/Middleware/EnsureMemberPasswordReset.php` exists
- `app/Livewire/Portal/PortalLogin.php` exists
- `resources/views/livewire/portal/portal-login.blade.php` exists
- `app/Livewire/Portal/PortalFirstPasswordReset.php` exists
- `resources/views/livewire/portal/portal-first-password-reset.blade.php` exists
- `resources/views/layouts/portal.blade.php` exists
- `tests/Feature/PortalAuthenticationAndTracerTest.php` exists
- Commits `5fc6be1` and `5ad9085` verified in git history

