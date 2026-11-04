---
phase: 08-official-email-imap-templates-and-workflow-rules
plan: 03
subsystem: mail
tags: [horizon, queue, deduplication, livewire, audit-log, credential-redaction]

requires:
  - phase: 08-official-email-imap-templates-and-workflow-rules
    provides: EmailThread, EmailMessage, EmailTemplate models, and EmailTemplateRenderer service

provides:
  - TemplatedLifecycleMail Mailable with RFC-5322 Message-ID and In-Reply-To headers
  - QueuedEmailDispatcher service with deterministic deduplication_key invariant and credential redaction
  - DispatchOfficialEmailJob Horizon queue worker job ($tries=3, $backoff=[30, 120, 300], ShouldBeUnique) with row-level lock idempotency
  - Livewire EmailDeliveryMonitor dashboard at /crm/{workspace}/settings/email-delivery with manual idempotent retry and immutable audit logging

affects:
  - 08-04-PLAN (Workflow Rules Engine & Automated Lifecycle Triggers)
  - 08-05-PLAN (End-to-End Phase 8 Verification)

actuals:
  tokens: 13728
  tasks: 2
  commits: 2
  plan_head_before: ef328b65a9079b37864c718d7f0621f9f7d366e9
  plan_head_after: 9043833e7658732b21c5861774d7d2b1bf9264ac

tech-stack:
  added: []
  patterns:
    - "Deterministic deduplication_key ({workspace_id}:{template_key}:{recipient_email}:{entity_key}) preventing duplicate EmailMessage rows and duplicate Horizon job dispatches"
    - "Pessimistic row-level lock (lockForUpdate) inside DispatchOfficialEmailJob skipping already-sent messages across Horizon worker retries"
    - "Transport exception credential redaction ([REDACTED]) before persisting failure_reason or official_email_failed audit logs"

key-files:
  created:
    - app/Domain/Mail/Mailables/TemplatedLifecycleMail.php
    - app/Domain/Mail/Services/QueuedEmailDispatcher.php
    - app/Jobs/DispatchOfficialEmailJob.php
    - app/Livewire/Mail/EmailDeliveryMonitor.php
    - resources/views/livewire/mail/email-delivery-monitor.blade.php
    - tests/Feature/QueuedEmailDeduplicationTest.php
    - tests/Feature/EmailDeliveryMonitorTest.php
  modified:
    - routes/web.php

key-decisions:
  - "Enforced dual-layer duplicate-send prevention combining QueuedEmailDispatcher deterministic deduplication_key lookups and DispatchOfficialEmailJob row-locked status === 'sent' short-circuit checks per MAIL-04 and T-08-08"
  - "Configured DispatchOfficialEmailJob with ShouldBeUnique, $tries = 3, and $backoff = [30, 120, 300] for resilient Horizon queue delivery"
  - "Sanitized all SMTP/IMAP transport failure messages via QueuedEmailDispatcher::sanitizeFailureMessage to replace URI credentials and password/secret/token parameters with [REDACTED] before writing to email_messages.failure_reason or audit_logs per DEC-11 and T-08-07"
  - "Protected /crm/{workspace}/settings/email-delivery with workspace.access, can:platform_services.manage_settings, and ValidatesWorkspaceOnHydration, logging official_email_retried audit events on manual retries per T-08-09"

patterns-established:
  - "Horizon queued lifecycle email dispatch via QueuedEmailDispatcher::dispatchTemplate with automatic EmailThread correlation"

requirements-completed:
  - MAIL-04

coverage:
  - id: D1
    description: "QueuedEmailDispatcher and DispatchOfficialEmailJob enforce deterministic deduplication, 3-attempt Horizon backoff, row-locked idempotent retry safety, and SMTP/IMAP credential redaction on transport failures"
    requirement: MAIL-04
    verification:
      - kind: integration
        ref: "tests/Feature/QueuedEmailDeduplicationTest.php#test_dispatch_template_prevents_duplicate_email_messages_and_duplicate_jobs_for_identical_milestone_events"
        status: pass
      - kind: integration
        ref: "tests/Feature/QueuedEmailDeduplicationTest.php#test_executing_dispatch_official_email_job_twice_on_same_message_is_idempotent_and_sends_only_once"
        status: pass
      - kind: integration
        ref: "tests/Feature/QueuedEmailDeduplicationTest.php#test_transport_exception_marks_message_failed_and_redacts_smtp_credentials_in_db_and_audit_log"
        status: pass
    human_judgment: false
  - id: D2
    description: "Livewire EmailDeliveryMonitor dashboard at /crm/{workspace}/settings/email-delivery with KPI counters, status/search filtering, audited manual retry for failed deliveries, duplicate-retry prevention on sent messages, and 403 tenancy/RBAC enforcement"
    requirement: MAIL-04
    verification:
      - kind: integration
        ref: "tests/Feature/EmailDeliveryMonitorTest.php#test_workspace_manager_can_view_email_delivery_monitor_and_filter_by_status_and_search"
        status: pass
      - kind: integration
        ref: "tests/Feature/EmailDeliveryMonitorTest.php#test_retrying_failed_message_requeues_dispatches_horizon_job_and_logs_audit_event"
        status: pass
      - kind: integration
        ref: "tests/Feature/EmailDeliveryMonitorTest.php#test_retrying_already_sent_message_is_refused_and_does_not_dispatch_duplicate_job"
        status: pass
      - kind: integration
        ref: "tests/Feature/EmailDeliveryMonitorTest.php#test_unauthorized_staff_and_cross_workspace_managers_receive_403"
        status: pass
    human_judgment: false

duration: 6 min
completed: 2026-11-04
status: complete
---

# Phase 08 Plan 03: Horizon Queued Email Dispatch, Idempotent Deduplication & Delivery Monitor Summary

**Horizon queued lifecycle email dispatcher (`QueuedEmailDispatcher`, `DispatchOfficialEmailJob`) with deterministic `deduplication_key` protection, credential-redacting failure handler, and Livewire `EmailDeliveryMonitor` dashboard.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-11-04T16:59:20Z
- **Completed:** 2026-11-04T17:05:10Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments

- Created `TemplatedLifecycleMail` Mailable rendering HTML content and setting RFC-5322 `Message-ID` and `In-Reply-To` headers for threaded email continuity.
- Built `QueuedEmailDispatcher` computing deterministic `deduplication_key` values (`{workspace_id}:{template_key}:{recipient_email}:{entity_key}`), preventing duplicate `EmailMessage` creation or duplicate Horizon job dispatches when `status` is `'queued'` or `'sent'`, linking messages to workspace `EmailThread` records, and redacting credentials in failure diagnostics via `sanitizeFailureMessage()`.
- Implemented `DispatchOfficialEmailJob` (`ShouldQueue`, `ShouldBeUnique`, `$tries = 3`, `$backoff = [30, 120, 300]`) acquiring a row-level lock (`lockForUpdate()`) to skip already-`'sent'` messages, recording `official_email_dispatched` audit logs on delivery, and recording sanitized `official_email_failed` audit logs on transport exceptions.
- Built Livewire `EmailDeliveryMonitor` component and Blade view at `/crm/{workspace}/settings/email-delivery` protected by `workspace.access`, `can:platform_services.manage_settings`, and `ValidatesWorkspaceOnHydration`, featuring Queued/Sent/Failed KPI cards, status and search filters, and safe manual retry (`official_email_retried`).

## Task Commits

Each task was committed atomically:

1. **Task 1: Horizon QueuedEmailDispatcher, Deterministic Deduplication Guard & DispatchOfficialEmailJob** - `e0508db` (feat)
2. **Task 2: Livewire EmailDeliveryMonitor Dashboard & Manual Idempotent Retry Action** - `9043833` (feat)

## Files Created/Modified

- `app/Domain/Mail/Mailables/TemplatedLifecycleMail.php` - Mailable carrying rendered bilingual subject, HTML body, and RFC-5322 threading headers.
- `app/Domain/Mail/Services/QueuedEmailDispatcher.php` - Queued template dispatch service with deterministic deduplication key guard and failure message credential redaction.
- `app/Jobs/DispatchOfficialEmailJob.php` - Horizon job with 3-attempt backoff, `ShouldBeUnique` lock, pessimistic row locking, and audit logging.
- `app/Livewire/Mail/EmailDeliveryMonitor.php` - Livewire administrative monitor for inspecting delivery statuses and triggering idempotent retries.
- `resources/views/livewire/mail/email-delivery-monitor.blade.php` - Responsive Blade view with KPI cards, status/search filters, sanitized failure diagnostics, and retry action button.
- `routes/web.php` - Registered `/crm/{workspace}/settings/email-delivery` route with `can:platform_services.manage_settings`.
- `tests/Feature/QueuedEmailDeduplicationTest.php` - Feature tests for deterministic deduplication, worker retry idempotency, and SMTP password redaction.
- `tests/Feature/EmailDeliveryMonitorTest.php` - Feature tests for delivery monitor KPIs, filters, manual retry workflow, duplicate-send refusal, and 403 tenancy/RBAC enforcement.

## Decisions Made

- Used `DB::table('email_messages')` and `DB::table('email_threads')` inside `QueuedEmailDispatcher` for cross-context deduplication and thread resolution while keeping `App\Domain` free of `withoutGlobalScopes` calls in accordance with `TenancyArchTest`.
- Enforced row-locked `status === 'sent'` short-circuiting inside `DispatchOfficialEmailJob::handle()` so concurrent or retried Horizon workers never send duplicate emails.
- Redacted both URI-embedded credentials (`smtp://user:pass@host`) and key-value secrets (`password=...`, `token=...`, `secret=...`) to `[REDACTED]` before saving `failure_reason` or writing `official_email_failed` audit logs per DEC-11 and T-08-07.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - Horizon queue worker configuration and mail transport settings operate using existing environment configuration.

## Next Phase Readiness

- `QueuedEmailDispatcher` and `DispatchOfficialEmailJob` are ready for Plan 08-04 (Workflow Rules Engine, 9-Lifecycle Event Listeners & Daily Renewal/Expiry Command).

## Self-Check: PASSED

- Verified all created and modified files exist on disk.
- Verified task commits `e0508db` and `9043833` exist in git history.
- Verified `php artisan test tests/Feature/QueuedEmailDeduplicationTest.php tests/Feature/EmailDeliveryMonitorTest.php tests/Arch/ModuleRegistryArchTest.php tests/Arch/TenancyArchTest.php` passes (12 tests, 61 assertions).

---
*Phase: 08-official-email-imap-templates-and-workflow-rules*
*Completed: 2026-11-04*
