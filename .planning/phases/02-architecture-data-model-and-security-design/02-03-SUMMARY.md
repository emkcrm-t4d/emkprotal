---
phase: 02-architecture-data-model-and-security-design
plan: 03
subsystem: architecture
tags:
  - security
  - stride-threat-model
  - owasp-asvs
  - append-only
  - mysql-triggers
  - private-storage
  - payment-adapter
  - payment-manager
  - bkash-manual
  - gateway-roadmap
requires:
  - ARCH-05
  - ARCH-06
provides:
  - SECURITY-SPEC.md (STRIDE threat register, ASVS Level 1 matrix, append-only triggers, encrypted private storage)
  - PAYMENT-ADAPTER-SPEC.md (decoupled payment adapter interface, driver manager, manual bKash driver, gateway roadmap)
affects:
  - phase-03 (Foundation security middleware, private storage disk, PaymentServiceProvider)
  - phase-04 (Multi-guard auth, Spatie RBAC, audit log core viewer, tenancy isolation)
  - phase-05 (FormRequest validation, 10-char bKash regex, minor parental consent upload)
  - phase-06 (Manual bKash payment verification, lead tri-state verification audit trail)
  - phase-07 (Invoices decoupling, post-conversion invoice generation)
  - phase-12 (SSLCommerz and direct bKash tokenized gateway extension)
tech-stack:
  added: []
  patterns:
    - OWASP ASVS Level 1 compliance baseline across 7 applicable categories
    - Comprehensive STRIDE threat model with actionable mitigations (T-02-01 to T-02-10, T-02-SC)
    - Database-enforced append-only audit trail via MySQL triggers rejecting UPDATE/DELETE with SQLSTATE 45000 (DEC-11)
    - Sensitive identity scan encryption at rest and storage outside public web root with zero symlinks (ARCH-06)
    - Authenticated controller streaming with workspace tenancy and Spatie RBAC checks (DocumentDownloadController)
    - Youth data protection tagging (youth_protection = true) and 5-year statutory retention lifecycle (DEC-09, GAP-P1-04)
    - Gang of Four Adapter Pattern and Laravel Manager Pattern for decoupled payment processing (DEC-10, ARCH-05)
    - 10-character uppercase alphanumeric bKash Transaction ID validation and dual-table uniqueness check (DEC-05)
key-files:
  created:
    - .planning/phases/02-architecture-data-model-and-security-design/SECURITY-SPEC.md
    - .planning/phases/02-architecture-data-model-and-security-design/PAYMENT-ADAPTER-SPEC.md
  modified: []
decisions:
  - "Formalize OWASP ASVS Level 1 controls across 7 applicable categories including multi-guard separation, forced password reset on first member login per DEC-06 (D-06), and zero dynamic code evaluation."
  - "Enforce append-only audit log immutability at the MySQL engine tier via triggers rejecting UPDATE and DELETE with SQLSTATE 45000 per DEC-11 (D-11)."
  - "Store applicant identity documents, passports, and consent scans on a private storage disk outside the web root under cryptographic UUID filenames with streaming mediated by DocumentDownloadController per DEC-09 (D-09) and ARCH-06."
  - "Decouple core domain models from payment processors via PaymentAdapterInterface and PaymentManager driver pattern per ARCH-05 and DEC-10 (D-10)."
  - "Implement ManualBkashPaymentAdapter validating exact 10-character uppercase alphanumeric regex (^[A-Z0-9]{10}$), asserting global uniqueness across leads and invoices, and eliminating redundant second-person reviews per DEC-05 (D-05)."
  - "Provide seamless Phase 12 gateway rollout path (SSLCommerz, direct bKash) requiring only driver configuration changes with zero core schema modifications."
metrics:
  duration: 7m
  completed_date: "2026-11-03"
status: complete
actuals:
  tokens: 15650
  tasks: 2
  commits: 2
  plan_head_before: 04503179b6ae6b41efd8942bf4cf99b885b6ed0b
  plan_head_after: f6f09680da356d93f1f570174e1044696dfce0bb
---

# Phase 02 Plan 03: Security Architecture, Immutability Triggers, & Decoupled Payment Adapter Specification Summary

**One-Liner:** Comprehensive application security architecture with OWASP ASVS Level 1 controls, complete STRIDE threat register, native MySQL append-only audit triggers, encrypted private storage layout, and decoupled pluggable payment adapter interface with manual bKash driver and Phase 12 gateway roadmap.

---

## Executive Summary

Phase 02 Plan 03 establishes the formal application security specifications, data protection safeguards, audit immutability guarantees, and decoupled payment driver architecture for the EMK Membership CRM platform within the Laravel 12 Hub monolith.

The plan satisfies requirements **ARCH-05** and **ARCH-06** by producing two comprehensive technical architecture contracts:
1. `SECURITY-SPEC.md`: Formalizes OWASP ASVS Level 1 controls across seven applicable categories, a comprehensive STRIDE threat register covering ten core scenarios plus supply-chain verification, native MySQL 8.4 database triggers enforcing append-only immutability on `audit_logs` rejecting `UPDATE` and `DELETE` queries with `SQLSTATE 45000` per **DEC-11 (D-11)**, Eloquent model event observer architecture with secret redaction, encrypted private document storage hierarchy outside the web root (`storage/app/private/workspaces/{ws}/...`) with zero web symlinks, authenticated controller streaming (`DocumentDownloadController`), and minor applicant privacy controls with a 5-year post-expiry retention schedule per **DEC-06 (D-06)**, **DEC-09 (D-09)**, and **GAP-P1-04**.
2. `PAYMENT-ADAPTER-SPEC.md`: Establishes the decoupled payment provider architecture isolating core entities (`Lead`, `Member`, `Invoice`) behind `PaymentAdapterInterface` using Laravel's Manager pattern (`PaymentManager`) per **ARCH-05** and **DEC-10 (D-10)**. Details the `ManualBkashPaymentAdapter` implementing 10-character uppercase alphanumeric regex validation (`^[A-Z0-9]{10}$`), dual-table uniqueness assertions across `leads.bkash_txid` and `invoices.payment_reference`, single-manager review authority, and tri-state verification evidence logging per **DEC-05 (D-05)**. Defines the future gateway extension architecture for `SslCommerzPaymentAdapter` and `BkashGatewayPaymentAdapter` enabling zero-schema-change gateway rollouts in Phase 12.

---

## Tasks Completed

| Task | Name | Type | Commit | Key Files Created |
|---|---|---|---|---|
| **1** | STRIDE Threat Register, OWASP ASVS Level 1 Controls, and Encrypted Storage Specification | `auto` | `8e2ec8e` | `.planning/phases/02-architecture-data-model-and-security-design/SECURITY-SPEC.md` |
| **2** | Decoupled Payment Adapter Interface, DTOs, and Manual bKash Driver | `auto` | `f6f0968` | `.planning/phases/02-architecture-data-model-and-security-design/PAYMENT-ADAPTER-SPEC.md` |

---

## Key Deliverables & Specifications Established

### 1. Security Architecture & Threat Model (`SECURITY-SPEC.md`)
- **OWASP ASVS Level 1 Baseline:**
  - **V2 Authentication:** Multi-guard separation (`web` vs `member`), Bcrypt ($\ge 12$) / Argon2id password hashing, forced password reset on first login (`must_reset_password = true`) per **DEC-06**, 120-minute idle session timeout, and rate-limited login endpoints.
  - **V3 Session Management:** `httpOnly = true`, `secure = true`, `sameSite = 'lax'` cookie policy; session regeneration on auth/privilege changes; Redis/session invalidation on conversion reversal.
  - **V4 Access Control:** Server-side Spatie RBAC; `TenantScope` query scoping; `DelegationPolicyService` hardcoded non-delegable managerial action allowlist.
  - **V5 Input Validation:** Typed `FormRequest` validation; 10-character uppercase bKash regex; cascading Bangladesh division/district whitelisting; file sniffing with 5MB cap.
  - **V6 Cryptography:** TLS 1.3 in transit; AES-256 encryption at rest; UUID v4 filename masking; zero hardcoded secrets.
  - **V8 Data Protection:** Minor data tagging (`youth_protection = true`), 5-year post-expiry retention schedule, Superadmin-exclusive hard delete per **DEC-11**, secret redaction in logs.
  - **V10 Malicious Code:** Zero dynamic code evaluation invariant (`eval()` banned); static 8-module central registry.
- **STRIDE Threat Register:**
  - 10 detailed threats analyzed with actionable mitigations: `T-02-01` (Cross-guard spoofing), `T-02-02` (Livewire property tampering), `T-02-03` (Conversion repudiation), `T-02-04` (Private storage file probing), `T-02-05` (Audit log tampering), `T-02-06` (bKash TxID recycling/forgery), `T-02-07` (RBAC delegation escalation), `T-02-08` (Youth data leakage), `T-02-09` (Public registration DoS), `T-02-10` (Member credential hijacking), plus `T-02-SC` (Supply-chain package screening).
- **Append-Only Immutability Contract (DEC-11 / D-11):**
  - Native MySQL triggers `trg_audit_logs_prevent_update` and `trg_audit_logs_prevent_delete` raising `SIGNAL SQLSTATE '45000'`.
  - Eloquent `AuditableObserver` capturing `actor_id`, `actor_guard`, `ip_address`, `user_agent`, `old_values`, `new_values` with sensitive key redaction (`password`, `remember_token`, `payment_secret`, `two_factor_secret`, `api_token`).
- **Encrypted Private Document Storage:**
  - Storage root at `storage/app/private/` outside public web root with zero symlinks.
  - Directory structure namespaced by workspace: `storage/app/private/workspaces/{workspace_id}/leads/{lead_uuid}/{uuid_v4}.{ext}`.
  - Access gated via `DocumentDownloadController` validating tenancy and Spatie permissions before streaming.

### 2. Decoupled Payment Adapter Architecture (`PAYMENT-ADAPTER-SPEC.md`)
- **Decoupling Rationale:**
  - Core domain models (`Lead`, `Member`, `Invoice`) contain zero processor-specific columns.
  - All payment operations pass through `PaymentAdapterInterface`.
- **PaymentAdapterInterface Contract:**
  - `getDriverName(): string`
  - `supportsManualVerification(): bool`
  - `verifyPayment(PaymentVerificationRequest $request): PaymentVerificationResult`
  - `initiatePayment(PaymentInitiationRequest $request): PaymentRedirectResponse`
  - `handleWebhook(Request $request): WebhookResult`
- **Driver Manager Pattern (`PaymentManager`):**
  - Extends Laravel's `Illuminate\Support\Manager`.
  - Dynamically resolves drivers based on `config('payments.default_driver')`.
  - Bound as a singleton in `PaymentServiceProvider`.
  - Includes `MockPaymentAdapter` for isolated automated test runs.
- **Manual bKash Driver (`ManualBkashPaymentAdapter`):**
  - Implements **DEC-05 (D-05)** launch requirements.
  - Validates exact 10-character uppercase alphanumeric regex: `^[A-Z0-9]{10}$`.
  - Enforces dual-table uniqueness check across `leads.bkash_txid` and `invoices.payment_reference`.
  - Validates reviewing staff has `leads.verify_payment` permission.
  - Generates structured JSON evidence payload for `lead_verifications` audit trail.
- **Phase 12 Gateway Roadmap (DEC-10 / D-10):**
  - Specifies `SslCommerzPaymentAdapter` (hosted session checkout, IPN webhook validation) and `BkashGatewayPaymentAdapter` (direct tokenized checkout API v1.2).
  - Outlines zero-schema-change transition roadmap requiring only driver configuration switch.

---

## Deviations from Plan

None. Plan executed exactly as specified.

---

## Known Stubs

None. All interfaces, DTOs, trigger DDLs, observer contracts, and architectural rules are fully specified with zero stubs or placeholders.

---

## Threat Surface Scan

| Flag | File | Description |
|---|---|---|
| threat_flag: database_triggers | `SECURITY-SPEC.md` | Native MySQL triggers on `audit_logs` enforce engine-level append-only immutability rejecting UPDATE/DELETE with SQLSTATE 45000. |
| threat_flag: private_storage | `SECURITY-SPEC.md` | Storage segregated outside web root (`storage/app/private/`); access requires authenticated streaming controller with tenancy check. |
| threat_flag: payment_isolation | `PAYMENT-ADAPTER-SPEC.md` | Decoupled adapter boundary isolates domain entities from payment credentials and processor APIs; bKash TxID validated via strict regex and dual-table uniqueness. |

---

## Self-Check: PASSED

1. **Created files exist:**
   - FOUND: `.planning/phases/02-architecture-data-model-and-security-design/SECURITY-SPEC.md`
   - FOUND: `.planning/phases/02-architecture-data-model-and-security-design/PAYMENT-ADAPTER-SPEC.md`
   - FOUND: `.planning/phases/02-architecture-data-model-and-security-design/02-03-SUMMARY.md`

2. **Commits exist in git history:**
   - FOUND: `8e2ec8e` (feat(02-03): specify security architecture, STRIDE threat model, and append-only audit triggers)
   - FOUND: `f6f0968` (feat(02-03): specify decoupled payment adapter interface and manual bKash driver)
