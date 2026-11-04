---
phase: 07-member-profiles-invoices-and-id-cards
plan: 03
status: complete
subsystem: crm-invoices
tags:
  - invoices
  - billing
  - renewal
  - livewire
  - receipts
  - tenancy
  - audit-logs
requires:
  - 07-02
provides:
  - invoice-service
  - invoice-directory-ui
  - printable-receipt-view
affects:
  - 07-04
  - 07-05
tech-stack:
  added: []
  patterns:
    - Pessimistic sequence locking for monotonic invoice numbers (INV-{YEAR}-{SEQ})
    - Global uniqueness validation on 10-char uppercase bKash TxIDs across leads and invoices
    - ValidatesWorkspaceOnHydration tenancy isolation
    - RFC 4180 streaming CSV export with spreadsheet formula injection neutralization
    - Printable receipt view with dedicated @media print CSS rules and QR verification box
key-files:
  created:
    - app/Domain/Invoices/Exceptions/DuplicatePaymentReferenceException.php
    - app/Domain/Invoices/Services/InvoiceService.php
    - app/Livewire/Invoices/InvoiceDirectory.php
    - resources/views/livewire/invoices/invoice-directory.blade.php
    - app/Http/Controllers/Invoices/InvoiceReceiptController.php
    - resources/views/invoices/receipt.blade.php
    - tests/Feature/InvoiceServiceTest.php
    - tests/Feature/InvoiceDirectoryTest.php
    - tests/Feature/InvoiceReceiptTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - routes/web.php
decisions:
  - Enforced exact 10-character uppercase alphanumeric regex ^[A-Z0-9]{10}$ on renewal bKash TxIDs per DEC-05
  - Enforced global duplicate reference guard across leads.bkash_txid and invoices.payment_reference, permitting reuse only if previous invoice was voided
  - Applied single-quote escaping on formula trigger characters (=, +, -, @) during CSV export per T-05-10
  - Enforced strict workspace scoping on invoice receipt requests with HTTP 403 on mismatch per DEC-01
  - Emitted membership_renewed, invoice_voided, and invoices_exported immutable audit events per DEC-11
metrics:
  duration: 18m
  completed_date: "2026-11-04"
actuals:
  tokens: 17500
  tasks: 3
  commits: 3
  plan_head_before: "4446dc73e00ca7d5d4654ac4b6d47cc989d3c3eb"
  plan_head_after: "86eee030e0c346059b5756349cf8f84d670bc839"
---

# Phase 07 Plan 03: Billing Lifecycle, Renewal Records, and Printable Receipt View Summary

**One-liner:** Monotonic sequential invoice generation and membership renewal engine with global bKash replay protection, paired with an interactive Livewire billing directory, formula-sanitized CSV export, and printable branded A4 receipts.

## Executive Summary

Plan 07-03 delivered the comprehensive billing lifecycle, renewal invoice records, and printable receipt subsystem per MEMB-04, DEC-03, DEC-05, and PAYMENT-ADAPTER-SPEC.md:
1. **Core Domain Invoice Service (`InvoiceService`):**
   - Monotonic sequential invoice numbering (`INV-{YEAR}-{5_DIGIT_SEQ}`) with pessimistic row-locking to eliminate race conditions under concurrent requests.
   - Post-conversion invoice creation linking members, originating leads, and verified payment terms.
   - Membership renewal invoicing coordinating with `MembershipValidityEngine` to advance validity dates (preserving remaining days for early renewals, resetting start dates for late renewals) and updating member statuses to `active`.
   - DEC-05 bKash Transaction ID validation enforcing exact 10-character uppercase alphanumeric strings (`^[A-Z0-9]{10}$`) and global uniqueness across `leads.bkash_txid` and `invoices.payment_reference`.
   - Invoice voiding with managerial authorization verification (`invoices.generate` / `workspace_manager`), state guards (only `paid`/`unpaid` can be voided), and audit trail recording.
2. **Interactive Livewire Invoice Directory (`InvoiceDirectory`):**
   - Route `/crm/{workspace}/invoices` guarded by `workspace.access` middleware and `can:invoices.view`.
   - Tenancy hydration verification via `ValidatesWorkspaceOnHydration`.
   - Reactive search across invoice numbers, bKash TxIDs, and member particulars.
   - Filter dropdowns by status (`paid`, `unpaid`, `voided`, `void_reversed`) and date ranges.
   - Audited streaming CSV export enforcing formula injection sanitization by prefixing `=`, `+`, `-`, or `@` with a single quote per T-05-10, logging `invoices_exported` audit trail entries per DEC-11.
3. **Printable Official Receipt Controller & Template (`InvoiceReceiptController` & `receipt.blade.php`):**
   - Route `/crm/{workspace}/invoices/{invoice}/receipt` with strict workspace tenancy boundary checks.
   - Clean, branded official receipt layout featuring American Spaces / EMK Center Dhaka official seal, member particulars, bKash transaction reference, itemized fee breakdown, and Taka amount in words.
   - Embedded SVG verification QR code box with public verification URL.
   - Print-optimized CSS (`@media print` rules) formatting for standard A4 portrait output with toolbar removal and crisp contrast.

## Tasks Executed

### Task 1: Core InvoiceService with Renewal Invoicing, Duplicate Reference Guard, and Voiding
- Added audit event constants `EVENT_MEMBERSHIP_RENEWED`, `EVENT_INVOICE_VOIDED`, and `EVENT_INVOICES_EXPORTED` to `AuditLog`.
- Created `App\Domain\Invoices\Exceptions\DuplicatePaymentReferenceException`.
- Implemented `App\Domain\Invoices\Services\InvoiceService` injecting `MembershipValidityEngine`.
- Implemented `generateInvoiceNumber`, `generatePostConversionInvoice`, `generateRenewalInvoice`, and `voidInvoice`.
- Created feature test `tests/Feature/InvoiceServiceTest.php` with 12 tests covering all generation, renewal, duplicate rejection, and voiding scenarios.
- **Commit:** `988711f` (`feat(07-03): implement core invoice service with renewal and voiding lifecycles`).

### Task 2: Livewire InvoiceDirectory Component with Status Filtering and Audited CSV Export
- Updated `routes/web.php` replacing `/crm/{workspace}/invoices` route with `InvoiceDirectory::class` guarded by `can:invoices.view`.
- Implemented `App\Livewire\Invoices\InvoiceDirectory` component with pagination, reactive filters, metrics, and formula-sanitized streaming CSV export.
- Created Tailwind Blade view `resources/views/livewire/invoices/invoice-directory.blade.php`.
- Created feature test `tests/Feature/InvoiceDirectoryTest.php` with 8 tests verifying authentication, authorization, status filtering, search, formula sanitization, and audit logging.
- **Commit:** `a2e8afb` (`feat(07-03): implement invoice directory component and audited csv export`).

### Task 3: Printable Invoice and PDF Receipt View with QR Verification Link
- Registered route `/crm/{workspace}/invoices/{invoice}/receipt` in `routes/web.php` pointing to `[InvoiceReceiptController::class, 'show']` guarded by `can:invoices.view`.
- Implemented `App\Http\Controllers\Invoices\InvoiceReceiptController` enforcing tenancy isolation and number-to-words currency conversion.
- Created printable Blade view `resources/views/invoices/receipt.blade.php` with A4 print CSS, official branding, and verification QR code box.
- Created feature test `tests/Feature/InvoiceReceiptTest.php` with 6 tests verifying rendering, data accuracy, and cross-workspace 403 protection.
- **Commit:** `86eee03` (`feat(07-03): implement printable invoice receipt controller and branded template`).

## Verification Results

Full automated test suite executed for Wave 3:
```
php artisan test tests/Feature/InvoiceServiceTest.php tests/Feature/InvoiceDirectoryTest.php tests/Feature/InvoiceReceiptTest.php
```
Output:
```
PASS  Tests\Feature\InvoiceServiceTest
✓ generate invoice number format and sequence                                                                  2.03s  
✓ generate post conversion invoice                                                                             0.36s  
✓ generate renewal invoice early renewal                                                                       0.36s  
✓ generate renewal invoice late renewal                                                                        0.35s  
✓ rejects invalid bkash txid format                                                                            0.36s  
✓ rejects lowercase characters in bkash txid                                                                   0.36s  
✓ rejects duplicate payment reference existing in leads                                                        0.33s  
✓ rejects duplicate payment reference existing in invoices                                                     0.34s  
✓ allows payment reference if prior invoice is voided                                                          0.37s  
✓ manager can void invoice with audit trail                                                                    0.45s  
✓ non manager cannot void invoice                                                                              0.41s  
✓ cannot void already voided invoice                                                                           0.41s  

PASS  Tests\Feature\InvoiceDirectoryTest
✓ unauthenticated request redirects or rejects                                                                 0.62s  
✓ staff without invoices view receives 403                                                                     0.56s  
✓ cross workspace staff receives 403                                                                           0.42s  
✓ authorized staff can view invoices index                                                                     0.72s  
✓ filtering by status isolates invoices                                                                        0.73s  
✓ search filters by invoice number and bkash txid                                                              0.57s  
✓ csv export generates sanitized stream and writes audit log                                                   0.58s  
✓ formula sanitization helper                                                                                  0.42s  

PASS  Tests\Feature\InvoiceReceiptTest
✓ unauthenticated request is redirected                                                                        0.52s  
✓ staff without invoices view permission is forbidden                                                          0.51s  
✓ cross workspace receipt request is rejected with 403                                                         0.45s  
✓ cross workspace route mismatch returns 403                                                                   0.49s  
✓ authorized staff can view receipt                                                                            0.52s  
✓ manager can view receipt                                                                                     0.52s  

Tests: 26 passed (75 assertions)
Duration: 14.56s
```

Regression test check across previous Wave 1 & 2 suites:
```
php artisan test tests/Feature/MemberDirectoryTest.php tests/Feature/MemberProfileTest.php tests/Unit/MembershipValidityEngineTest.php tests/Feature/MembershipTermManagementTest.php
```
Output:
```
Tests: 32 passed (157 assertions)
Duration: 16.58s
```

## Deviations from Plan

### Auto-fixed Issues
1. **[Rule 1 - Bug] Lead creation in test setUp required submitted_at attribute**
   - **Found during:** Task 1 test run
   - **Issue:** SQLite table definition has `NOT NULL` constraint on `leads.submitted_at`. Test fixture was missing this field.
   - **Fix:** Added `'submitted_at' => Carbon::now()` to test lead setup.
   - **Files modified:** `tests/Feature/InvoiceServiceTest.php`
   - **Commit:** `988711f`

2. **[Rule 1 - Bug] Removed non-existent space relationship on Workspace model in InvoiceReceiptController**
   - **Found during:** Task 3 test run
   - **Issue:** `InvoiceReceiptController` called `Workspace::with('space')->find(...)`, but `Workspace` model contains `name` directly without a `space` relation.
   - **Fix:** Changed call to `Workspace::find($workspaceId)` and accessed `$workspaceModel?->name`.
   - **Files modified:** `app/Http/Controllers/Invoices/InvoiceReceiptController.php`
   - **Commit:** `86eee03`

## Known Stubs
None - all invoice lifecycles, database locks, Livewire reactive filters, formula injection sanitization, receipt rendering, and audit logging pathways are fully wired and verified.

## Threat Surface Verification
- **T-07-07 (Repudiation / Fraud):** `InvoiceService::generateRenewalInvoice` validates exact 10-char uppercase regex `^[A-Z0-9]{10}$` and verifies global uniqueness across both `leads.bkash_txid` and active `invoices.payment_reference`, blocking payment replay fraud.
- **T-07-08 (Tampering):** `InvoiceDirectory::exportCsv` neutralizes spreadsheet formula injection risks by prepending `'` to fields beginning with `=`, `+`, `-`, or `@`.
- **T-07-09 (Information Disclosure):** `InvoiceReceiptController` asserts `$invoiceModel->workspace_id === $workspaceId` and verifies staff workspace access, rejecting cross-workspace receipt requests with HTTP 403.

## Self-Check: PASSED
- [x] `app/Domain/Invoices/Services/InvoiceService.php` exists
- [x] `app/Domain/Invoices/Exceptions/DuplicatePaymentReferenceException.php` exists
- [x] `app/Livewire/Invoices/InvoiceDirectory.php` exists
- [x] `resources/views/livewire/invoices/invoice-directory.blade.php` exists
- [x] `app/Http/Controllers/Invoices/InvoiceReceiptController.php` exists
- [x] `resources/views/invoices/receipt.blade.php` exists
- [x] `tests/Feature/InvoiceServiceTest.php` exists
- [x] `tests/Feature/InvoiceDirectoryTest.php` exists
- [x] `tests/Feature/InvoiceReceiptTest.php` exists
- [x] Commit `988711f` verified in git history
- [x] Commit `a2e8afb` verified in git history
- [x] Commit `86eee03` verified in git history
