---
phase: 07-member-profiles-invoices-and-id-cards
plan: 01
status: complete
subsystem: crm-members
tags:
  - livewire
  - members
  - tenancy
  - audit-logs
  - profile-inspector
requires:
  - 06-06
provides:
  - member-directory
  - member-profile
  - edit-member-contact-modal
affects:
  - 07-02
  - 07-03
  - 07-04
  - 07-05
tech-stack:
  added: []
  patterns:
    - Layer 2 ValidatesWorkspaceOnHydration
    - TenantScope BelongsToTenant scoping
    - Livewire 3 sub-component modal with dispatch events
    - 31-field structured inspection grid
    - Immutable audit trail recording with before/after snapshots
key-files:
  created:
    - app/Livewire/Members/MemberDirectory.php
    - resources/views/livewire/members/member-directory.blade.php
    - app/Livewire/Members/MemberProfile.php
    - resources/views/livewire/members/member-profile.blade.php
    - app/Livewire/Members/EditMemberContactModal.php
    - resources/views/livewire/members/edit-member-contact-modal.blade.php
    - tests/Feature/MemberDirectoryTest.php
    - tests/Feature/MemberProfileTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - app/Domain/Members/Models/Member.php
    - routes/web.php
decisions:
  - Bound /crm/{workspace}/members to MemberDirectory protected by workspace.access middleware and can:members.view
  - Bound /crm/{workspace}/members/{member:uuid} to MemberProfile with getRouteKeyName returning uuid
  - Enforced strict core identity immutability (name, DOB, email, member_id) on EditMemberContactModal
  - Logged member_contact_updated events to immutable audit_logs with JSON snapshots per DEC-11
metrics:
  duration: 18m
  completed_date: "2026-11-04"
actuals:
  tokens: 31924
  tasks: 3
  commits: 3
  plan_head_before: "a9f0297d15281633f9033d6266fd0c103aa82991"
  plan_head_after: "87813335a9315155bd40be34d98a9acfda205aab"
---

# Phase 07 Plan 01: Member Profiles Directory and Inspector Summary

**One-liner:** Workspace-isolated Livewire Member Directory, 31-field Member Profile Inspector with verified document streaming and billing history, and inline contact editing modal with field immutability guards and append-only audit logging.

## Executive Summary

Plan 07-01 delivered the primary Member Management user interface and tracer subsystem for Phase 7:
1. **Member Management Directory (`MemberDirectory`):** Implemented responsive Livewire directory component at `/crm/{workspace}/members` scoped by `BelongsToTenant` and `ValidatesWorkspaceOnHydration`. Features live debounced search across Member ID, applicant name, email, and contact number, status multi-facet filtering (active, inactive, restricted, revoked), membership term duration filtering, column sorting, pagination (15 records/page), and summary metrics counters.
2. **31-Field Member Profile Inspector (`MemberProfile`):** Implemented detailed member inspection view at `/crm/{workspace}/members/{member:uuid}`. Displays profile header card with Member ID badge, status pill, term validity dates, and three interactive tabs:
   - **31-Field Overview:** Structured inspection across personal demographics, contact/address details, academic/professional credentials, and verified identity documents with secure streaming links via `DocumentDownloadController`.
   - **Invoices & Billing History:** Displays post-conversion and renewal billing records with BDT amounts, payment references (bKash TxID), payment method, status, and paid timestamps.
   - **Activity Timeline & Audit Trail:** Unified chronological lifecycle timeline displaying card issuance stages, membership activation, and detailed audit event records.
3. **Contact Details Inline Editing Modal (`EditMemberContactModal`):** Embedded Livewire dialog guarded by `can:members.edit`. Permits updating contact numbers, address fields, and occupation/study details while strictly protecting core identity fields (name, email, DOB, member_id) from alteration. Records before/after JSON state snapshots in `audit_logs` with event type `member_contact_updated` per DEC-11.

## Tasks Executed

### Task 1: End-to-End Member Directory and Tenancy Tracer Slice
- Replaced `/crm/{workspace}/members` route with `\App\Livewire\Members\MemberDirectory::class` guarded by `can:members.view`.
- Implemented `App\Livewire\Members\MemberDirectory` with pagination, sorting, status/term filters, and debounced search.
- Created `resources/views/livewire/members/member-directory.blade.php` with metrics cards, filter toolbar, responsive table, and status badges.
- Created `tests/Feature/MemberDirectoryTest.php` verifying authentication, tenancy isolation, search, status filtering, and sorting (6 tests passed, 50 assertions).
- **Commit:** `db7f9d2` (`feat(07-01): implement member directory and tenancy tracer slice`).

### Task 2: 31-Field Member Profile Inspector with Verified Document Previews and Billing History
- Added `/crm/{workspace}/members/{member:uuid}` route pointing to `\App\Livewire\Members\MemberProfile::class` with `can:members.view`.
- Implemented `App\Livewire\Members\MemberProfile` eager loading `lead.attachments`, `term`, `invoices`, `idCards`, and `user`.
- Created `resources/views/livewire/members/member-profile.blade.php` with 31-field layout, document streaming links, invoices tab, and timeline.
- Created `tests/Feature/MemberProfileTest.php` testing 31 fields, document previews, billing records, timeline, and cross-workspace 403.
- **Commit:** `12bde98` (`feat(07-01): implement 31-field member profile inspector and activity tabs`).

### Task 3: Member Contact Details Inline Editing Modal with Immutable Audit Logging
- Implemented `App\Livewire\Members\EditMemberContactModal` with strict validation, `members.edit` permission check, and audit logging.
- Created `resources/views/livewire/members/edit-member-contact-modal.blade.php` with immutability banner and form inputs.
- Embedded modal in `MemberProfile` listening for `member-contact-updated` event.
- Extended `tests/Feature/MemberProfileTest.php` with 3 test cases asserting contact editing, permission rejection (403), core identity immutability, and audit log generation (8 tests passed, 47 assertions).
- **Commit:** `8781333` (`feat(07-01): implement member contact details inline editing modal and audit logging`).

## Verification Results

Full automated test suite executed for Phase 7 Wave 1:
```
php artisan test tests/Feature/MemberDirectoryTest.php tests/Feature/MemberProfileTest.php
```
Output:
```
PASS  Tests\Feature\MemberDirectoryTest
✓ unauthenticated requests redirect to login
✓ authorized staff can view member directory route
✓ cross workspace staff receives 403 tenancy defense
✓ search filtering by member id name email and contact
✓ status filtering isolates active inactive restricted and revoked
✓ sorting and reset filters

PASS  Tests\Feature\MemberProfileTest
✓ authorized staff can view member profile and 31 fields
✓ verified document previews and download links
✓ invoices tab displays billing records
✓ activity timeline tab displays lifecycle events
✓ cross workspace member inspection returns 403
✓ staff with members edit can update contact and address details
✓ staff without members edit cannot invoke modal or save
✓ core identity fields remain strictly immutable

Tests: 14 passed (97 assertions)
Duration: 9.24s
```

## Deviations from Plan

### Auto-fixed Issues
1. **[Rule 1 - Bug] Added getRouteKeyName to Member model**
   - **Found during:** Task 2 implementation
   - **Issue:** Binding `{member:uuid}` in Livewire components could fail default key resolution if not explicitly mapped.
   - **Fix:** Added `getRouteKeyName(): string { return 'uuid'; }` to `Member` model.
   - **Files modified:** `app/Domain/Members/Models/Member.php`
   - **Commit:** `12bde98`

2. **[Rule 2 - Auto-add] Added EVENT_MEMBER_CONTACT_UPDATED to AuditLog model**
   - **Found during:** Task 3 implementation
   - **Issue:** `AuditLog` constants lacked explicit token for member contact update events.
   - **Fix:** Added `public const EVENT_MEMBER_CONTACT_UPDATED = 'member_contact_updated';`.
   - **Files modified:** `app/Domain/Audit/Models/AuditLog.php`
   - **Commit:** `8781333`

## Known Stubs
None - all data sources, relationships, and action handlers are fully implemented and verified.

## Threat Surface Verification
- **T-07-01 (Information Disclosure):** Workspace tenancy verified via `TenantScope` and `ValidatesWorkspaceOnHydration`. Cross-workspace requests return HTTP 403.
- **T-07-02 (Elevation of Privilege):** `EditMemberContactModal` requires `members.edit` permission. Core identity fields (`name`, `email`, `DOB`, `member_id`) are omitted from updates and confirmed immutable by tests.
- **T-07-03 (Information Disclosure):** Verified document downloads route through `DocumentDownloadController` enforcing workspace isolation and `leads.view_documents` / `leads.view_youth_records` permissions.

## Self-Check: PASSED
- [x] `app/Livewire/Members/MemberDirectory.php` exists
- [x] `resources/views/livewire/members/member-directory.blade.php` exists
- [x] `app/Livewire/Members/MemberProfile.php` exists
- [x] `resources/views/livewire/members/member-profile.blade.php` exists
- [x] `app/Livewire/Members/EditMemberContactModal.php` exists
- [x] `resources/views/livewire/members/edit-member-contact-modal.blade.php` exists
- [x] `tests/Feature/MemberDirectoryTest.php` exists
- [x] `tests/Feature/MemberProfileTest.php` exists
- [x] Commit `db7f9d2` verified in git history
- [x] Commit `12bde98` verified in git history
- [x] Commit `8781333` verified in git history
