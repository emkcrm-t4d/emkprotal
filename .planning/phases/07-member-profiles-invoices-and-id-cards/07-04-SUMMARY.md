---
phase: 07-member-profiles-invoices-and-id-cards
plan: 04
status: complete
subsystem: crm-id-cards
tags: [id-cards, lifecycle, mail, livewire, csv-export, audit-logs]
requires: [07-02, 07-03]
provides: [id-card-lifecycle-service, card-ready-mail, id-card-operations-panel]
affects: [07-05]
key-files:
  created:
    - app/Domain/IdCards/Services/IdCardLifecycleService.php
    - app/Mail/CardReadyNotificationMail.php
    - app/Livewire/IdCards/IdCardDirectory.php
    - resources/views/livewire/id-cards/id-card-directory.blade.php
    - tests/Feature/IdCardLifecycleServiceTest.php
    - tests/Feature/IdCardNotificationTest.php
    - tests/Feature/IdCardBatchOperationsTest.php
  modified:
    - routes/web.php
commits: 3
plan_head_before: 8b343d8f1774cacdaba7ba6d58065da7b08b30c1
plan_head_after: 038bf0d
metrics:
  completed: 2026-11-04
  tests: 16 passed (52 assertions)
---

# Phase 7 Plan 04: ID Card Lifecycle, Notification, and Operations Panel Summary

Three-stage physical ID card state machine (provided_for_printing → card_printed_ready_for_pickup → delivered) with queued pickup email and a staff panel for batch PVC CSV export, batch print marking, and front-desk delivery.

## Tasks

| Task | Name | Commit |
|------|------|--------|
| 1 | IdCardLifecycleService enforcing sequential states, audit logging | 0c84fd4 |
| 2 | Queued CardReadyNotificationMail dispatched on print | 07e86ac |
| 3 | IdCardDirectory Livewire panel, route `crm.id_cards.index`, tests | 038bf0d |

## Key Points
- Route `/crm/{workspace}/id-cards` guarded by `can:members.manage_cards`; component re-asserts workspace and permission in mount, hydrate, and every action.
- `exportBatchForPrinting` streams CSV with formula-injection sanitization (`'` prefix for `= + - @`), batch ref `BATCH-YYYYMM-NNN`, and writes `id_cards_batch_exported` audit event.
- All card queries are scoped by `workspace_id`; delivering a card from another workspace returns 404.

## Deviations from Plan
None of substance. Notes: the CSV "Photo URL / Download Reference" column holds the member ID as the reference because no member photo URL field exists on Member; batch reference COUNT is the number of selected cards.

## Known Stubs
None.

## Self-Check: PASSED
All files exist; commits 0c84fd4, 07e86ac, 038bf0d on main; 16 tests pass.
