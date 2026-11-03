---
phase: "02"
slug: "architecture-data-model-and-security-design"
status: draft
nyquist_compliant: false
wave_0_complete: false
created: "2026-11-03"
---

# Phase 02 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest PHP 3.x / PHPUnit 11 |
| **Config file** | `phpunit.xml` (instantiated in Phase 3) |
| **Quick run command** | `php vendor/bin/pest --filter ArchitectureTest` |
| **Full suite command** | `php vendor/bin/pest` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run quick assertion check
- **After every plan wave:** Run full validation suite
- **Before `/gsd-verify-work`:** All architectural contracts and schema specifications verified
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | ARCH-01 | T-02-01 | Static module registry rejects dynamic code eval | Arch / Contract | `node -e "const fs = require('fs'); const content = fs.readFileSync('.planning/phases/02-architecture-data-model-and-security-design/MODULE-REGISTRY.md', 'utf8'); if (!content.includes('ModuleRegistry') || !content.includes('declared_actions') || !content.includes('eval(')) process.exit(1);"` | ❌ W0 | ⬜ pending |
| 02-01-02 | 01 | 1 | ARCH-02 | T-02-02 | Relational schema enforces unique bKash TxID and `zoho_id` | Integration / Schema | `node -e "const fs = require('fs'); const content = fs.readFileSync('.planning/phases/02-architecture-data-model-and-security-design/SCHEMA-SPEC.md', 'utf8'); if (!content.includes('CREATE TABLE spaces') || !content.includes('zoho_id') || !content.includes('uq_leads_bkash_txid')) process.exit(1);"` | ❌ W0 | ⬜ pending |
| 02-02-01 | 02 | 1 | ARCH-03 | T-02-03 | Tenancy isolation blocks cross-workspace queries | Security / Tenancy | `node -e "const fs = require('fs'); const content = fs.readFileSync('.planning/phases/02-architecture-data-model-and-security-design/TENANCY-SPEC.md', 'utf8'); if (!content.includes('TenantScope') || !content.includes('VerifyWorkspaceAccess') || !content.includes('Livewire')) process.exit(1);"` | ❌ W0 | ⬜ pending |
| 02-02-02 | 02 | 1 | ARCH-04 | T-02-04 | 10-step atomic conversion transaction and 30-day reversal | Feature / Invariant | `node -e "const fs = require('fs'); const content = fs.readFileSync('.planning/phases/02-architecture-data-model-and-security-design/CONVERSION-SPEC.md', 'utf8'); if (!content.includes('ConversionAllowed') || !content.includes('lockForUpdate') || !content.includes('30-day')) process.exit(1);"` | ❌ W0 | ⬜ pending |
| 02-03-01 | 03 | 2 | ARCH-05 | T-02-05 | STRIDE threat model and ASVS Level 1 controls | Security / ThreatModel | `node -e "const fs = require('fs'); const content = fs.readFileSync('.planning/phases/02-architecture-data-model-and-security-design/SECURITY-SPEC.md', 'utf8'); if (!content.includes('STRIDE') || !content.includes('ASVS') || !content.includes('append-only')) process.exit(1);"` | ❌ W0 | ⬜ pending |
| 02-03-02 | 03 | 2 | ARCH-06 | T-02-06 | Replaceable payment adapter decouples domain models | Unit / Contract | `node -e "const fs = require('fs'); const content = fs.readFileSync('.planning/phases/02-architecture-data-model-and-security-design/PAYMENT-ADAPTER-SPEC.md', 'utf8'); if (!content.includes('PaymentAdapterInterface') || !content.includes('ManualBkashPaymentAdapter')) process.exit(1);"` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Arch/ModuleRegistryTest.php` — Pest architecture test asserting no `eval()`, verifying module keys.
- [ ] `tests/Feature/DatabaseSchemaTest.php` — Migration integrity and foreign key tests.
- [ ] `tests/Feature/ConversionInvariantTest.php` — Test all permutations of tri-state verification and lead status.
- [ ] `tests/Feature/ConversionReversalTest.php` — 30-day boundary and reason validation tests.
- [ ] `tests/Feature/TenancyIsolationTest.php` — Cross-workspace query and Livewire hydration security tests.

---

## Manual-Only Verifications

| Behavior | Why Manual | Verification Method |
|----------|------------|---------------------|
| Multi-workspace boundary navigation | User permission and UI routing experience | Verify Admin vs Manager login views in staging |
