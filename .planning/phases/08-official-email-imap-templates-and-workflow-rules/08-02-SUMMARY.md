---
phase: 08-official-email-imap-templates-and-workflow-rules
plan: 02
subsystem: mail
tags: [email-templates, bilingual, zero-eval, livewire, audit-log, xss-defense]

requires:
  - phase: 08-official-email-imap-templates-and-workflow-rules
    provides: Phase 8 email_templates database table and TenantEntityInterface foundation

provides:
  - EmailTemplate tenant-scoped Eloquent model with 9 canonical lifecycle milestone constants
  - EmailTemplateRenderer zero-eval safe variable interpolation engine (en, bn, bilingual) with HTML-escaping
  - EmailTemplateSeeder populating English and Bengali copy for all 9 lifecycle milestones per workspace
  - Livewire EmailTemplateManager administrative screen at /crm/{workspace}/settings/email-templates with live preview modal and immutable audit logging

affects:
  - 08-03-PLAN (Queued Outbound Email Delivery & Lead/Member Profile Integration)
  - 08-04-PLAN (Workflow Rules Engine & Automated Lifecycle Triggers)

actuals:
  tokens: 15891
  tasks: 2
  commits: 2
  plan_head_before: 2b4508210c9fad14e2167a594bcd4b8f4265c8c0
  plan_head_after: 76184dc69cd1131c8f1f55815473c7a53688961d

tech-stack:
  added: []
  patterns:
    - "Zero-eval deterministic placeholder interpolation via strtr over allowlisted tokens (ALLOWED_PLACEHOLDERS)"
    - "HTML-escaping (htmlspecialchars ENT_QUOTES | ENT_SUBSTITUTE) of all variable values injected into body_html"
    - "Bilingual template rendering supporting English ('en'), Bengali ('bn'), and combined ('bilingual') output"

key-files:
  created:
    - app/Domain/Mail/Models/EmailTemplate.php
    - app/Domain/Mail/Services/EmailTemplateRenderer.php
    - database/seeders/EmailTemplateSeeder.php
    - app/Livewire/Mail/EmailTemplateManager.php
    - resources/views/livewire/mail/email-template-manager.blade.php
    - tests/Unit/EmailTemplateRendererTest.php
    - tests/Feature/EmailTemplateManagerTest.php
  modified:
    - app/Domain/Audit/Models/AuditLog.php
    - database/seeders/DatabaseSeeder.php
    - routes/web.php

key-decisions:
  - "Enforced zero-eval placeholder replacement in EmailTemplateRenderer using strtr over a fixed 9-token allowlist (applicant_name, member_name, member_id, workspace_name, expiry_date, invoice_number, amount_bdt, reason, action_url) per DEC-02 and T-08-04"
  - "HTML-escaped all interpolated variable values before substitution into body_html and stripped <script> tags on template save to prevent SSTI and Stored XSS per T-08-05"
  - "Seeded complete English and Bengali subjects and bodies for all 9 canonical lifecycle milestones per MAIL-03"
  - "Guarded /crm/{workspace}/settings/email-templates with workspace.access, can:platform_services.manage_settings, and ValidatesWorkspaceOnHydration, recording email_template_updated events in audit_logs"

patterns-established:
  - "Zero-eval database template rendering via EmailTemplateRenderer::render with locale modes ('en', 'bn', 'bilingual')"

requirements-completed:
  - MAIL-03

coverage:
  - id: D1
    description: "Zero-eval EmailTemplateRenderer safely interpolating allowlisted variables in English, Bengali, and Bilingual modes while escaping XSS payloads and ignoring non-allowlisted tokens"
    requirement: MAIL-03
    verification:
      - kind: unit
        ref: "tests/Unit/EmailTemplateRendererTest.php#test_interpolates_all_nine_allowlisted_placeholders_in_en_bn_and_bilingual_modes"
        status: pass
      - kind: unit
        ref: "tests/Unit/EmailTemplateRendererTest.php#test_escapes_html_and_xss_payloads_in_variable_values"
        status: pass
      - kind: unit
        ref: "tests/Unit/EmailTemplateRendererTest.php#test_never_evaluates_non_allowlisted_placeholders_or_dynamic_expressions"
        status: pass
    human_judgment: false
  - id: D2
    description: "Canonical 9-milestone bilingual EmailTemplateSeeder and Livewire EmailTemplateManager UI with live preview modal, script sanitization, RBAC/tenancy 403 enforcement, and immutable audit logging"
    requirement: MAIL-03
    verification:
      - kind: integration
        ref: "tests/Feature/EmailTemplateManagerTest.php#test_workspace_manager_can_access_email_template_manager_and_view_all_nine_milestones"
        status: pass
      - kind: integration
        ref: "tests/Feature/EmailTemplateManagerTest.php#test_editing_bilingual_template_persists_changes_strips_scripts_and_logs_audit_event"
        status: pass
      - kind: integration
        ref: "tests/Feature/EmailTemplateManagerTest.php#test_open_preview_renders_interpolated_sample_variables_in_en_bn_and_bilingual_modes"
        status: pass
      - kind: integration
        ref: "tests/Feature/EmailTemplateManagerTest.php#test_unauthorized_staff_and_cross_workspace_managers_receive_403"
        status: pass
    human_judgment: false

duration: 8 min
completed: 2026-11-04
status: complete
---

# Phase 08 Plan 02: Bilingual Lifecycle Email Template Manager, Zero-Eval Renderer & 9-Milestone Seeder Summary

**Bilingual 9-milestone lifecycle email template catalog, zero-eval `EmailTemplateRenderer` with HTML-escaped variable interpolation, and Livewire `EmailTemplateManager` UI with live EN/BN/Bilingual preview and immutable audit logging.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-11-04T16:49:35Z
- **Completed:** 2026-11-04T16:57:10Z
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments

- Created `EmailTemplate` model (`TenantEntityInterface`, `BelongsToTenant`) defining the 9 canonical lifecycle milestone constants (`application_receipt`, `missing_items`, `application_rejection`, `conversion_confirmation`, `portal_activation`, `id_card_ready`, `renewal_window`, `membership_expiry`, `request_outcome`).
- Built `EmailTemplateRenderer` performing deterministic `strtr` replacement strictly restricted to the 9 allowlisted placeholders (`applicant_name`, `member_name`, `member_id`, `workspace_name`, `expiry_date`, `invoice_number`, `amount_bdt`, `reason`, `action_url`), HTML-escaping all variable values (`htmlspecialchars`) in `body_html`, stripping unsupplied allowlisted tokens, and leaving unknown expressions untouched with zero `eval()` or dynamic Blade compilation.
- Created `EmailTemplateSeeder` (and registered in `DatabaseSeeder`) seeding complete English and Bengali subjects and bodies for all 9 lifecycle milestones per workspace.
- Implemented Livewire `EmailTemplateManager` component and view at `/crm/{workspace}/settings/email-templates` guarded by `workspace.access` and `can:platform_services.manage_settings`, enabling bilingual template editing, `<script>` tag sanitization, live sample variable previews in English, Bengali, and Bilingual modes, and append-only `email_template_updated` audit logging.

## Task Commits

Each task was committed atomically:

1. **Task 1: Bilingual EmailTemplate Model, Zero-Eval Variable Renderer & 9-Milestone Seeder** - `a55b2d7` (feat)
2. **Task 2: Livewire EmailTemplateManager UI with Bilingual Preview Modal & Audit Logging** - `76184dc` (feat)

## Files Created/Modified

- `app/Domain/Mail/Models/EmailTemplate.php` - Tenant-scoped model with canonical 9-milestone constants and JSON/boolean casts.
- `app/Domain/Mail/Services/EmailTemplateRenderer.php` - Zero-eval variable interpolation service supporting `'en'`, `'bn'`, and `'bilingual'` modes with XSS escaping.
- `database/seeders/EmailTemplateSeeder.php` - Seeder provisioning all 9 bilingual lifecycle templates per workspace.
- `database/seeders/DatabaseSeeder.php` - Registered `EmailTemplateSeeder`.
- `app/Domain/Audit/Models/AuditLog.php` - Added Phase 8 audit constants (`EVENT_EMAIL_TEMPLATE_UPDATED`, `EVENT_WORKFLOW_RULE_UPDATED`, `EVENT_OFFICIAL_EMAIL_DISPATCHED`, `EVENT_OFFICIAL_EMAIL_FAILED`, `EVENT_OFFICIAL_EMAIL_RETRIED`).
- `app/Livewire/Mail/EmailTemplateManager.php` - Livewire administrative manager for bilingual template editing, `<script>` stripping, live preview, and audit logging.
- `resources/views/livewire/mail/email-template-manager.blade.php` - Responsive Blade view displaying all 9 milestone templates, inline bilingual editor, and EN/BN/Bilingual preview modal.
- `routes/web.php` - Registered `/crm/{workspace}/settings/email-templates` route with `can:platform_services.manage_settings`.
- `tests/Unit/EmailTemplateRendererTest.php` - Unit tests verifying allowlisted placeholder interpolation, XSS escaping, non-allowlisted token safety, and 9-milestone seeder completeness.
- `tests/Feature/EmailTemplateManagerTest.php` - Feature tests verifying 9-milestone display, bilingual editing, audit trail creation, live preview modes, and 403 tenancy/RBAC enforcement.

## Decisions Made

- Used deterministic `strtr` replacement over `EmailTemplateRenderer::ALLOWED_PLACEHOLDERS` intersected with `$template->allowed_variables` so templates stored in the database can never trigger PHP code execution or Server-Side Template Injection (SSTI) per DEC-02 and T-08-04.
- HTML-escaped all interpolated variable values before substitution into `body_html` while keeping `subject` and `body_text` plain-text safe per T-08-05.
- Stripped `<script>` blocks when saving template bodies in `EmailTemplateManager::saveTemplate` and recorded `AuditLog::EVENT_EMAIL_TEMPLATE_UPDATED` entries with `old_values` and `new_values`.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - all 9 bilingual email templates are seeded automatically via `EmailTemplateSeeder` (and lazily ensured on first visit to `EmailTemplateManager`).

## Next Phase Readiness

- `EmailTemplate` and `EmailTemplateRenderer` are ready for Plan 08-03 (`OutboundEmailService`, `SendOfficialNotificationJob`, and manual/automated dispatch integration).

## Self-Check: PASSED

- Verified all created and modified files exist on disk.
- Verified task commits `a55b2d7` and `76184dc` exist in git history.
- Verified `php artisan test tests/Unit/EmailTemplateRendererTest.php tests/Feature/EmailTemplateManagerTest.php tests/Arch/ModuleRegistryArchTest.php` passes (11 tests, 100 assertions).

---
*Phase: 08-official-email-imap-templates-and-workflow-rules*
*Completed: 2026-11-04*
