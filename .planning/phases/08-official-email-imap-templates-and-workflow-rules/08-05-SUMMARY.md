---
phase: 08-official-email-imap-templates-and-workflow-rules
plan: 05
subsystem: mail
tags: [email-threads, workflow-rules, livewire, horizon, runbook, e2e-testing]

requires:
  - phase: 08-official-email-imap-templates-and-workflow-rules
    provides: EmailThreadPanel, EmailTemplateManager, QueuedEmailDispatcher, EmailDeliveryMonitor, WorkflowRuleEngine, and WorkflowRuleManager

provides:
  - Embedded EmailThreadPanel on LeadProfile and MemberProfile with Official Emails tab navigation and pre-conversion thread continuity
  - Domain lifecycle hooks in LeadIngestionService, LeadCommunicationService, LeadConversionService, and IdCardLifecycleService triggering WorkflowRuleEngine
  - Production operations runbook (docs/runbooks/EMAIL-AND-HORIZON-RUNBOOK.md) covering IMAP/SMTP sync, secret rotation, Horizon supervision, and milestone scheduling
  - Holistic Phase 8 End-to-End Integration Test Suite (EndToEndOfficialMailboxAndWorkflowsTest) verifying MAIL-01 through MAIL-05

affects:
  - Phase 09 (Read-Only Member Portal, Change Requests, and Renewals)
  - Phase 12 (Verification, Security Review, Release, and Later Payment Gateway)

actuals:
  tokens: 11666
  tasks: 3
  commits: 3
  plan_head_before: d3ec16995a7e803b5b61173edfaf382ce4168f6d
  plan_head_after: 2f5053a06b148bc568cb5dd8675b47eae260caec

tech-stack:
  added: []
  patterns:
    - "Fault-isolated domain lifecycle workflow triggers wrapped in try/catch blocks with deterministic entityKey deduplication"
    - "Unified pre-conversion Lead and post-conversion Member email thread rendering on MemberProfile via linked lead_id"

key-files:
  created:
    - docs/runbooks/EMAIL-AND-HORIZON-RUNBOOK.md
    - tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php
  modified:
    - app/Livewire/Leads/LeadProfile.php
    - resources/views/livewire/leads/lead-profile.blade.php
    - app/Livewire/Members/MemberProfile.php
    - resources/views/livewire/members/member-profile.blade.php
    - app/Domain/Forms/Services/LeadIngestionService.php
    - app/Domain/Leads/Services/LeadCommunicationService.php
    - app/Domain/Leads/Services/LeadConversionService.php
    - app/Domain/IdCards/Services/IdCardLifecycleService.php

key-decisions:
  - "Embedded EmailThreadPanel directly on both LeadProfile and MemberProfile with dedicated Official Emails tab navigation while passing both memberId and originating leadId on MemberProfile so pre-conversion correspondence remains visible per MAIL-02"
  - "Wired LeadIngestionService, LeadCommunicationService, LeadConversionService, and IdCardLifecycleService into WorkflowRuleEngine::trigger inside fault-isolated try/catch blocks while preserving existing Mailable dispatches for full backward compatibility with Phase 5, 6, and 7 tests per T-08-14"
  - "Documented zero-downtime IMAP/SMTP credential rotation, Horizon queue supervision, and dual-layer deduplication invariants in docs/runbooks/EMAIL-AND-HORIZON-RUNBOOK.md without exposing real credentials per DEC-11 and T-08-15"

patterns-established:
  - "Fault-isolated domain service hooks invoking WorkflowRuleEngine::trigger with deterministic entityKey identifiers"

requirements-completed:
  - MAIL-01
  - MAIL-02
  - MAIL-03
  - MAIL-04
  - MAIL-05

coverage:
  - id: D1
    description: "Embedded EmailThreadPanel on LeadProfile and MemberProfile displaying chronological inbound/outbound correspondence across Lead-to-Member conversion"
    requirement: MAIL-01
    verification:
      - kind: e2e
        ref: "tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php#test_scenario_1_bidirectional_imap_sync_and_threaded_profile_display_across_lead_to_member_conversion"
        status: pass
    human_judgment: false
  - id: D2
    description: "Domain lifecycle hooks in LeadIngestionService, LeadCommunicationService, LeadConversionService, and IdCardLifecycleService recording threaded correspondence via WorkflowRuleEngine"
    requirement: MAIL-02
    verification:
      - kind: e2e
        ref: "tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php#test_scenario_1_bidirectional_imap_sync_and_threaded_profile_display_across_lead_to_member_conversion"
        status: pass
      - kind: integration
        ref: "tests/Feature/EndToEndMemberLifecycleTest.php"
        status: pass
    human_judgment: false
  - id: D3
    description: "Bilingual lifecycle template customization, live EN/BN/Bilingual preview, and immutable audit logging"
    requirement: MAIL-03
    verification:
      - kind: e2e
        ref: "tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php#test_scenario_2_bilingual_template_customization_and_safe_preview"
        status: pass
    human_judgment: false
  - id: D4
    description: "Horizon queued email dispatch, deterministic deduplication across retries, SMTP secret redaction, and EmailDeliveryMonitor manual retry"
    requirement: MAIL-04
    verification:
      - kind: e2e
        ref: "tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php#test_scenario_3_horizon_queued_delivery_retry_deduplication_and_secret_redaction"
        status: pass
    human_judgment: false
  - id: D5
    description: "Automated workflow rules, 30-day renewal window notifications, expired member inactive status transition, and 403 cross-workspace tenancy isolation"
    requirement: MAIL-05
    verification:
      - kind: e2e
        ref: "tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php#test_scenario_4_automated_workflow_rules_and_expiry_renewal_scheduler"
        status: pass
      - kind: e2e
        ref: "tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php#test_scenario_5_cross_workspace_tenancy_isolation_returns_403"
        status: pass
    human_judgment: false

duration: 6 min
completed: 2026-11-04
status: complete
---

# Phase 08 Plan 05: Profile Email Thread Integration, Lifecycle Hooks, Operations Runbook & Holistic E2E Suite Summary

**Embedded `EmailThreadPanel` on `LeadProfile` and `MemberProfile`, wired domain lifecycle services into `WorkflowRuleEngine`, authored `EMAIL-AND-HORIZON-RUNBOOK.md`, and verified `MAIL-01` through `MAIL-05` with a 5-scenario holistic E2E test suite.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-11-04T17:17:40Z
- **Completed:** 2026-11-04T17:23:35Z
- **Tasks:** 3
- **Files modified:** 10

## Accomplishments

- Embedded `livewire:mail.email-thread-panel` and **Official Emails** tab navigation (`emails`) into both `LeadProfile` (`/crm/{workspace}/leads/{lead}`) and `MemberProfile` (`/crm/{workspace}/members/{member:uuid}`), passing both `memberId` and originating `leadId` on `MemberProfile` so pre-conversion Lead correspondence and post-conversion Member correspondence render in one unified timeline.
- Wired `LeadIngestionService`, `LeadCommunicationService` (`sendMissingItemsNotification`, `rejectLead`), `LeadConversionService` (`convert`), and `IdCardLifecycleService` (`markPrinted`) into `WorkflowRuleEngine::trigger` inside fault-isolated `try/catch` blocks while preserving existing Mailable dispatches for 100% backward compatibility with Phase 5, 6, and 7 tests.
- Authored `docs/runbooks/EMAIL-AND-HORIZON-RUNBOOK.md` covering Information Desk IMAP/SMTP architecture, RFC-5322 threading, zero-downtime credential rotation without UI/DB exposure (`DEC-11`), Horizon queue supervision, dual-layer deduplication invariants, and daily `crm:process-membership-milestones` operations.
- Created `tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php` verifying all 5 end-to-end scenarios (`MAIL-01` through `MAIL-05` and cross-workspace `403` isolation).

## Task Commits

Each task was committed atomically:

1. **Task 1: Embed Threaded Email Panel on Lead & Member Profiles and Wire Domain Lifecycle Triggers** - `19cb9cf` (feat)
2. **Task 2: Author Official Email, IMAP & Horizon Operations Runbook** - `d95b649` (docs)
3. **Task 3: Holistic Phase 8 End-to-End Integration Test Suite** - `2f5053a` (test)

## Files Created/Modified

- `app/Livewire/Leads/LeadProfile.php` - Added `'emails'` tab to `$validTabs` in `setTab()`.
- `resources/views/livewire/leads/lead-profile.blade.php` - Added `Official Emails` tab button and embedded `livewire:mail.email-thread-panel`.
- `app/Livewire/Members/MemberProfile.php` - Added `'emails'` tab to `$validTabs` in `setTab()`.
- `resources/views/livewire/members/member-profile.blade.php` - Added `Official Emails` tab button and embedded `livewire:mail.email-thread-panel` with `memberId` and `leadId`.
- `app/Domain/Forms/Services/LeadIngestionService.php` - Triggered `WorkflowRule::TRIGGER_LEAD_SUBMITTED` upon public application ingestion.
- `app/Domain/Leads/Services/LeadCommunicationService.php` - Triggered `WorkflowRule::TRIGGER_LEAD_MISSING_ITEMS` and `WorkflowRule::TRIGGER_LEAD_REJECTED` on applicant communications.
- `app/Domain/Leads/Services/LeadConversionService.php` - Triggered `WorkflowRule::TRIGGER_LEAD_CONVERTED` and `WorkflowRule::TRIGGER_PORTAL_ACTIVATED` on atomic lead-to-member conversion.
- `app/Domain/IdCards/Services/IdCardLifecycleService.php` - Triggered `WorkflowRule::TRIGGER_ID_CARD_READY` when marking physical ID cards printed.
- `docs/runbooks/EMAIL-AND-HORIZON-RUNBOOK.md` - Production operations runbook for IMAP/SMTP sync, secret rotation, Horizon supervision, and workflow rules.
- `tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php` - Holistic 5-scenario E2E integration test suite for Phase 8.

## Decisions Made

- Embedded `EmailThreadPanel` on both `LeadProfile` and `MemberProfile` so staff can inspect chronological threads and send audited replies directly from either profile view.
- Wrapped domain lifecycle `WorkflowRuleEngine::trigger` calls in fault-isolated `try/catch (\Throwable)` blocks gated by `Schema::hasTable('workflow_rules')` per `T-08-14` so mail configuration errors never abort atomic database transactions.
- Documented IMAP/SMTP secret rotation and Horizon queue supervision using placeholder secret values (`***REDACTED_SECRET***`) per `T-08-15` and `DEC-11`.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - see `docs/runbooks/EMAIL-AND-HORIZON-RUNBOOK.md` for production environment variable reference.

## Next Phase Readiness

- Phase 8 (`MAIL-01` through `MAIL-05`) is 100% complete and verified across 38 unit, feature, E2E, and architecture tests (302 assertions).
- Ready for Phase 9 (Read-Only Member Portal, Change Requests, and Renewals), which will consume `EmailThread` history for read-only member communication views and `WorkflowRule::TRIGGER_REQUEST_OUTCOME` for change request outcome notifications.

## Self-Check: PASSED

- Verified all created and modified files exist on disk.
- Verified task commits `19cb9cf`, `d95b649`, and `2f5053a` exist in git history.
- Verified `php artisan test tests/Feature/EndToEndOfficialMailboxAndWorkflowsTest.php tests/Feature/MailboxSyncAndThreadTracerTest.php tests/Unit/EmailTemplateRendererTest.php tests/Feature/EmailTemplateManagerTest.php tests/Feature/QueuedEmailDeduplicationTest.php tests/Feature/EmailDeliveryMonitorTest.php tests/Feature/WorkflowRuleEngineTest.php tests/Feature/WorkflowRuleManagerTest.php tests/Arch/ModuleRegistryArchTest.php tests/Arch/TenancyArchTest.php` passes (38 tests, 302 assertions).

---
*Phase: 08-official-email-imap-templates-and-workflow-rules*
*Completed: 2026-11-04*
