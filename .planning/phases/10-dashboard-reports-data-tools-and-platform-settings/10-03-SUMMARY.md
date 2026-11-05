---
phase: 10-dashboard-reports-data-tools-and-platform-settings
plan: 03
subsystem: platform
tags: [settings, parameters, encryption, aes-256, masking, livewire, audit, asvs]
requires:
  - "10-02"
provides:
  - "platform-settings-service"
  - "system-parameters-schema"
  - "encrypted-credentials-layer"
  - "platform-settings-hub"
affects:
  - "operational-settings"
  - "security-controls"
  - "administration"
tech-stack:
  added: []
  patterns:
    - "AES-256 string encryption at rest via Crypt facade for cast_type=encrypted (T-10-08)"
    - "Sensitive parameter presentation masking returning '••••••••' per ASVS V8.2"
    - "DEC-11 immutable audit trail logging with sensitive token scrubbing to '[REDACTED]'"
    - "Hierarchical workspace fallback resolving workspace-level overrides before global parameters"
    - "Livewire 3 tabbed settings hub with role-guarded non-delegable authorization (T-10-07)"
key-files:
  created:
    - database/migrations/2026_11_05_000001_create_platform_settings_table.php
    - app/Domain/Platform/Models/PlatformSetting.php
    - app/Domain/Platform/Services/PlatformSettingsService.php
    - app/Livewire/Platform/PlatformSettingsHub.php
    - resources/views/livewire/platform/platform-settings-hub.blade.php
    - tests/Feature/PlatformSettingsServiceTest.php
    - tests/Feature/PlatformSettingsHubTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - routes/web.php
decisions:
  - "Store system parameters in platform_settings supporting typed casting (string, integer, boolean, json, encrypted) and workspace-specific overrides (DASH-04)."
  - "Encrypt credentials at rest using AES-256 via Crypt::encryptString() and mask values in presentation views as '••••••••' per T-10-08."
  - "Scrub sensitive parameter values to '[REDACTED]' in audit log old_values and new_values payloads per DEC-11 (D-11) and ASVS V8.2."
  - "Restrict access to platform settings strictly to users possessing platform_services.manage_settings; delegated staff are blocked with HTTP 403 (T-10-07)."
  - "Connect administrative sub-modules (terms, staff, email templates, delivery monitor, workflow rules, audit logs, and system parameters) in a unified navigation interface."
metrics:
  duration: "16 minutes"
  completed_date: "2026-11-05"
status: complete
actuals:
  tokens: 44000
  tasks: 2
  commits: 2
  plan_head_before: "04f4b9ce16c1cb278fb49bfa254364b23db7486c"
  plan_head_after: "5e9c531416d4f5472c514c26046b2004a3adede9"
---

# Phase 10 Plan 03: Unified Platform Settings Hub & System Parameters Engine Summary

Dynamic system parameter engine, AES-256 credential encryption layer, sensitive presentation masking, immutable audit logging with token scrubbing, and unified Livewire Platform Settings Hub per DASH-04, DEC-11 (D-11), T-10-07, and T-10-08.

## Objectives Delivered

1. **System Parameters Schema (`platform_settings`)**:
   - Migration `2026_11_05_000001_create_platform_settings_table.php` establishing `platform_settings` table.
   - Supports key, nullable `workspace_id` (foreign key to `workspaces` with cascade delete; `null` represents global default fallback), group (`general`, `membership`, `payments`, `security`, `notifications`), value, `cast_type` (`string`, `integer`, `boolean`, `json`, `encrypted`), `is_secret` flag, and description.
   - Enforces unique constraint `uq_settings_workspace_key` on `[workspace_id, key]` and index `idx_settings_workspace_group` on `[workspace_id, group]`.

2. **PlatformSetting Eloquent Model & Masking Layer**:
   - Implements `TenantEntityInterface` with customized `BelongsToTenant` boot scoping that seamlessly permits global parameter fallback without violating architecture rules (`withoutGlobalScope` prohibition).
   - Typed getter and setter mutators supporting automatic conversion across string, integer, boolean, JSON, and encrypted types.
   - AES-256 encryption at rest via `Crypt::encryptString()` and `Crypt::decryptString()` for `cast_type === 'encrypted'`.
   - Accessor `getMaskedValueAttribute()` returning `'••••••••'` when `is_secret` is `true`.

3. **PlatformSettingsService Engine & Sensitive Secret Scrubbing**:
   - `get(string $key, ?string $workspaceId = null, mixed $default = null)`: hierarchically evaluates workspace-specific overrides first, then global settings, before falling back to `$default`.
   - `set(string $key, mixed $value, ?string $workspaceId = null, ...)`: finds or instantiates parameter records and dispatches `AuditLog::EVENT_PLATFORM_SETTING_UPDATED`.
   - Critical Security Layer (DEC-11 / ASVS V8.2): scrubs sensitive parameter tokens to `'[REDACTED]'` in both `old_values` and `new_values` payloads so plain secrets never leak to audit logs.
   - `getGroup(string $group, ?string $workspaceId = null)`: resolves key-value mapping with workspace overrides merged over global defaults.
   - `seedDefaults()`: idempotently populates baseline organization, membership, payment, and security parameters.

4. **Unified Platform Settings Hub Livewire Component & Blade View**:
   - Livewire 3 component `PlatformSettingsHub` mounted at `/crm/{workspace}/settings` with `/crm/{workspace}/platform_services` redirect alias.
   - Non-delegable authorization check enforcing `platform_services.manage_settings` and workspace boundary tenancy isolation (delegated staff and cross-workspace requests receive HTTP 403).
   - Sub-navigation linking all administrative modules: System Parameters (active), Membership Terms, Staff & Delegations, Email Templates, Email Delivery Monitor, Workflow Rules, and Audit Logs.
   - Tabbed parameter panels for General, Membership, Payments, and Security settings.
   - Secret parameter masking in form state, preventing accidental overwriting when values are unchanged.
   - Input validation enforcing required names, valid emails, and positive durations/fees.

5. **Comprehensive Automated Test Suites**:
   - `tests/Feature/PlatformSettingsServiceTest.php`: 6 tests, 34 assertions verifying all cast types, workspace overrides, AES-256 database encryption, audit log redaction, and default seeding.
   - `tests/Feature/PlatformSettingsHubTest.php`: 7 tests, 42 assertions verifying access control, delegated staff rejection (403), cross-workspace blocking (403), tab switching, parameter persistence, and validation error handling.

## Key Changes and Commits

| Task | Description | Commit Hash | Key Files |
|------|-------------|-------------|-----------|
| Task 1 | System parameters schema, encrypted model, and platform settings service with secret redaction | `33a14d9` | `database/migrations/2026_11_05_000001_create_platform_settings_table.php`, `app/Domain/Platform/Models/PlatformSetting.php`, `app/Domain/Platform/Services/PlatformSettingsService.php`, `app/Domain/Audit/Models/AuditLog.php`, `tests/Feature/PlatformSettingsServiceTest.php` |
| Task 2 | Unified platform settings hub Livewire component with tabbed navigation and role-guarded access | `5e9c531` | `app/Livewire/Platform/PlatformSettingsHub.php`, `resources/views/livewire/platform/platform-settings-hub.blade.php`, `routes/web.php`, `tests/Feature/PlatformSettingsHubTest.php` |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Domain architecture encapsulation encapsulation violation on global collect() helper**
- **Found during:** Task 1 architecture test execution (`tests/Arch/ModuleRegistryArchTest.php`)
- **Issue:** `PlatformSettingsService::getGroupModels` invoked global `collect()`, which is prohibited in `App\Domain` by Pest architecture rules.
- **Fix:** Instantiated `new \Illuminate\Support\Collection()` directly.
- **Files modified:** `app/Domain/Platform/Services/PlatformSettingsService.php`
- **Commit:** `33a14d9`

**2. [Rule 1 - Bug] Nested dot-notation binding mismatch in Livewire form state**
- **Found during:** Task 2 test execution (`PlatformSettingsHubTest`)
- **Issue:** Flat array keys containing dots (e.g. `settings['general.organization_name']`) conflicted with Livewire's recursive dot-notation model hydration (e.g. `wire:model="settings.general.organization_name"`), causing validation errors on sub-array parameters.
- **Fix:** Refactored `$this->settings` to structured nested array indexed by group and key (`settings[group][field]`).
- **Files modified:** `app/Livewire/Platform/PlatformSettingsHub.php`, `tests/Feature/PlatformSettingsHubTest.php`
- **Commit:** `5e9c531`

## Verification Results

- `php artisan test tests/Feature/PlatformSettingsServiceTest.php tests/Feature/PlatformSettingsHubTest.php`: **13 passed, 76 assertions (100%)**
- `php artisan test tests/Feature/WorkspaceDataExportTest.php tests/Feature/OperationalDashboardTest.php tests/Feature/OperationalDashboardTenancyTest.php`: **32 passed, 167 assertions (100%)**
- `php artisan test tests/Arch/`: **5 passed, 12 assertions (100%)**

## Threat Mitigations Applied

| Threat ID | Category | Component | Status | Mitigation Applied |
|-----------|----------|-----------|--------|---------------------|
| T-10-07 | Elevation of Privilege | PlatformSettingsHub | Mitigated | Enforced `platform_services.manage_settings` via Spatie permission guard and workspace tenancy checks; unauthorized staff receive HTTP 403. |
| T-10-08 | Information Disclosure | PlatformSetting & AuditLog | Mitigated | Encrypted sensitive parameters at rest via AES-256, rendered `'••••••••'` in presentation views, and scrubbed secret tokens to `'[REDACTED]'` in audit logs. |
| T-10-09 | Tampering | PlatformSettingsService & Hub | Mitigated | Enforced strict server-side validation and casting rules on parameters prior to persistence. |

## Self-Check: PASSED

- All 7 created and 2 modified files exist on disk.
- Commits `33a14d9` and `5e9c531` verified in git history.
- Zero architecture, security, or tenancy regressions detected.
