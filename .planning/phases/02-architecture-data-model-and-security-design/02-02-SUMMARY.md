---
phase: 02-architecture-data-model-and-security-design
plan: 02
subsystem: architecture
tags:
  - tenancy-defense
  - multi-tenant
  - livewire-hydration
  - global-scope
  - atomic-transaction
  - pessimistic-locking
  - lead-conversion
  - conversion-reversal
  - audit-log
requires:
  - ARCH-03
  - ARCH-04
provides:
  - TENANCY-SPEC.md (four-layer multi-workspace defense architecture)
  - CONVERSION-SPEC.md (10-step atomic conversion transaction & 30-day reversal protocol)
affects:
  - phase-04 (VerifyWorkspaceAccess middleware, TenantScope, BelongsToTenant trait)
  - phase-06 (LeadConversionService, ConversionReversalService, tri-state gating)
  - phase-07 (Member master profiles, dynamic terms, and ID cards)
  - phase-09 (Member portal user provisioning and session invalidation)
tech-stack:
  added: []
  patterns:
    - Four-layer defense-in-depth multi-workspace isolation (DEC-01)
    - Livewire component hydration parameter inspection against model tampering (T-02-03)
    - Eloquent TenantScope query injection with strict ban on withoutGlobalScope
    - Namespaced private document storage and authorized controller streaming
    - Unyielding Tri-State Verification gating invariant (DEC-03)
    - Deadlock-free pessimistic sequence locking hierarchy (ARCH-03)
    - 10-step atomic Lead-to-Member conversion transaction (DEC-08, DEC-11)
    - 30-day statutory conversion reversal with cascading rollback (DEC-04)
key-files:
  created:
    - .planning/phases/02-architecture-data-model-and-security-design/TENANCY-SPEC.md
    - .planning/phases/02-architecture-data-model-and-security-design/CONVERSION-SPEC.md
  modified: []
decisions:
  - "Enforce multi-workspace tenancy across four server layers (Route Middleware, Livewire Hydration Guard, Eloquent TenantScope, and Private Storage Streaming) launching with ws-emk-dhaka per DEC-01 (D-01)."
  - "Strictly ban withoutGlobalScope() across all controllers, Livewire components, and reports via automated Pest Architecture tests."
  - "Require all three verification streams (Payment, Document, Photo) to be verified and lead approved before conversion is permitted per DEC-03 (D-03)."
  - "Acquire pessimistic row-level lock on member_id_sequences before leads to guarantee deadlock-free, monotonic Member ID allocation."
  - "Compute dynamic membership dates from membership_terms with start_date defaulting to Manager approval date per DEC-08 (D-08)."
  - "Permit conversion reversals strictly within 30 calendar days to Workspace Managers with minimum 20-character justification and cascading entity rollback per DEC-04 (D-04)."
metrics:
  duration: 6m
  completed_date: "2026-11-03"
status: complete
actuals:
  tokens: 11800
  tasks: 2
  commits: 2
  plan_head_before: 9cfe49d425f6633154ededa1e7aeabd52f09d996
  plan_head_after: 5121fc19dea2209513627268b3ef19ce1561ad8e
---

# Phase 02 Plan 02: Tenancy Defense & Atomic Conversion Invariant Specification Summary

**One-Liner:** Four-layer server-side tenancy defense specification paired with 10-step atomic lead-to-member conversion transaction and 30-day audited reversal protocol.

---

## Executive Summary

Phase 02 Plan 02 delivers the core security and domain invariant specifications for the EMK Membership CRM platform within the Laravel 12 Hub monolith. 

The implementation satisfies requirements **ARCH-03** and **ARCH-04** by producing two comprehensive technical architecture contracts:
1. `TENANCY-SPEC.md`: Specifies the four-layer defense-in-depth multi-workspace isolation model launching with EMK Center Dhaka (`ws-emk-dhaka`) per **DEC-01 (D-01)**. Combines route-level verification (`VerifyWorkspaceAccess`), Livewire component hydration inspection (`ValidatesWorkspaceOnHydration`), Eloquent global query scoping (`TenantScope` with a hard ban on `withoutGlobalScope`), and private filesystem namespacing (`/private/workspaces/{workspace_id}/...`) with controller-guarded streaming.
2. `CONVERSION-SPEC.md`: Formalizes the mathematical ConversionAllowed gating invariant per **DEC-03 (D-03)**, under-18 parental consent verification per **DEC-09 (D-09)**, deadlock-free pessimistic locking hierarchy (`member_id_sequences` before `leads`), monotonic Member ID sequence generator (`EMK-2026-XXXXX`), the 10-step atomic conversion transaction, and the 30-day statutory conversion reversal protocol with cascading rollback and append-only audit logging per **DEC-04 (D-04)** and **DEC-11 (D-11)**.

---

## Tasks Completed

| Task | Name | Type | Commit | Key Files Created |
|---|---|---|---|---|
| **1** | Four-Layer Multi-Workspace Tenancy Guard Architecture | `auto` | `ad87c92` | `.planning/phases/02-architecture-data-model-and-security-design/TENANCY-SPEC.md` |
| **2** | 10-Step Atomic Lead-to-Member Conversion Transaction and 30-Day Reversal Protocol | `auto` | `5121fc1` | `.planning/phases/02-architecture-data-model-and-security-design/CONVERSION-SPEC.md` |

---

## Key Deliverables & Specifications Established

### 1. Multi-Workspace Tenancy Guard Architecture (`TENANCY-SPEC.md`)
- **Defense-in-Depth Rationale:** Rejects reliance on client parameters or single filters; establishes four independent server-side barriers.
- **Layer 1: Route Middleware (`VerifyWorkspaceAccess`):**
  - Intercepts `{workspace}` route parameter across all `/crm/{workspace}/*` routes.
  - Validates `auth()->user()->workspace_id === {workspace}` for all non-Superadmin users.
  - Aborts immediately with `HTTP 403 Forbidden` on mismatch, logging security context.
- **Layer 2: Livewire Hydration Guard (`ValidatesWorkspaceOnHydration` trait):**
  - Hooks into component hydration cycle on every network request before action execution.
  - Recursively inspects public component models and entity IDs (`$leadId`, `$memberId`), aborting with `HTTP 403` on tenant ID tampering.
- **Layer 3: Eloquent Global Scope (`TenantScope`):**
  - Automatically appends `WHERE workspace_id = auth()->user()->workspace_id` to all `SELECT`, `UPDATE`, and `DELETE` queries on tenant models.
  - Bypasses scope only for `superadmin` and `global_admin` users.
  - Hard Rule: Strict ban on `withoutGlobalScope()` across controllers, Livewire components, and reports enforced via Pest Architecture tests.
- **Layer 4: Storage Namespacing & Streaming Barrier:**
  - Files stored strictly in `storage/app/private/workspaces/{workspace_id}/...`.
  - Direct web symlinks prohibited; files streamed via `DocumentDownloadController` after asserting authentication, workspace matching, and Spatie permissions.
- **Staging Pool Architecture:** Non-EMK selections (`workspace_id = NULL`) route to Superadmin staging pool, invisible to `ws-emk-dhaka` staff.

### 2. Atomic Conversion & 30-Day Reversal Protocol (`CONVERSION-SPEC.md`)
- **Conversion Gating Invariant (DEC-03):**
  $$\text{ConversionAllowed} = (\text{Payment} = \text{'verified'}) \land (\text{Document} = \text{'verified'}) \land (\text{Photo} = \text{'verified'}) \land (\text{Lead} = \text{'approved'})$$
  - Enforces mandatory parental consent upload for applicants under 18 per **DEC-09**.
  - Asserts global member email uniqueness to prevent fragmented accounts.
  - Throws `UnmetConversionInvariantException` on any unsatisfied condition.
- **Concurrency & Deadlock Prevention:**
  - Lock acquisition hierarchy: lock `member_id_sequences` row first (`SELECT ... FOR UPDATE`), then lock `leads` row second.
  - Monotonic alphanumeric Member ID: `{SPACE_CODE}-{YEAR}-{5_DIGIT_SEQ}` (e.g. `EMK-2026-00001`).
- **10-Step Atomic Conversion Sequence:**
  1. Invariant verification (`isConversionAllowed`).
  2. Sequence lock and Member ID allocation.
  3. Member profile instantiation.
  4. Dynamic date calculation (`start_date = now()`, `expiry_date = start_date + term.duration_months` per DEC-08).
  5. Secure document re-association to `/members/{member_uuid}/`.
  6. Post-conversion invoice generation (`status = 'paid'`, bKash TxID reference per DEC-03).
  7. ID card queueing (`status = 'provided_for_printing'`).
  8. Lead transition to `status = 'converted'` with `converted_member_id`.
  9. Read-only portal account provisioning (`guard = 'member'`, `must_reset_password = true` per DEC-06) and welcome email job queueing via Horizon.
  10. Immutable audit log entry `conversion_completed` emitted per DEC-11.
- **30-Day Statutory Conversion Reversal Protocol (DEC-04):**
  - Statutory 30-calendar-day window cutoff.
  - Purity of state assertion (0 subsequent invoices, 0 renewals, card not delivered).
  - Mandatory justification of at least 20 characters.
  - Cascading rollback: Member to `archived_reversed`, portal account disabled and sessions revoked, invoice marked `void_reversed`, card marked `voided_reversal`, Lead restored to `under_review` preserving verification streams, and immutable audit event `conversion_reversed`.

---

## Deviations from Plan

None — plan executed exactly as written.

---

## Verification & Self-Check

### 1. Created Files
- `[x]` `d:/EMK_Protal/.planning/phases/02-architecture-data-model-and-security-design/TENANCY-SPEC.md` — FOUND
- `[x]` `d:/EMK_Protal/.planning/phases/02-architecture-data-model-and-security-design/CONVERSION-SPEC.md` — FOUND

### 2. Commit Existence
- `[x]` `ad87c92`: feat(02-02): specify four-layer multi-workspace tenancy defense architecture — FOUND
- `[x]` `5121fc1`: feat(02-02): specify 10-step atomic conversion transaction and 30-day reversal protocol — FOUND

### 3. Automated Assertions
- `[x]` Task 1 automated verify command: PASSED
- `[x]` Task 2 automated verify command: PASSED

## Self-Check: PASSED
