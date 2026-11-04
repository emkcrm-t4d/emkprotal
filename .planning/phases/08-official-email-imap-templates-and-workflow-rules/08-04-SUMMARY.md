---
phase: 08-official-email-imap-templates-and-workflow-rules
plan: 04
subsystem: mail
tags: [workflow-rules, zero-eval, renewal-window, membership-expiry, livewire, audit-log]

requires:
  - phase: 08-official-email-imap-templates-and-workflow-rules
    provides: EmailTemplate model, EmailTemplateSeeder, and QueuedEmailDispatcher service

provides:
  - WorkflowRule tenant-scoped Eloquent model with 9 allowlisted lifecycle trigger constants
  - WorkflowRuleSeeder seeding all 9 canonical workflow rules per workspace linked to EmailTemplates
  - WorkflowRuleEngine zero-eval rule evaluator and daily membership milestone processor
  - ProcessMembershipMilestonesCommand (crm:process-membership-milestones) scheduled daily at 01:00
  - Livewire WorkflowRuleManager administrative UI at /crm/{workspace}/settings/workflow-rules with immutable audit logging

affects:
  - 08-05-PLAN (End-to-End Phase 8 Verification)
  - Phase 09 (Read-Only Member Portal, Change Requests, and Renewals)

actuals:
  tokens: 16392
  tasks: 2
  commits: 2
  plan_head_before: 3a749e8509c155e90b1651029839d485de2590be
  plan_head_after: ea0bba5a29d6ec2e10053707fa566240f57a3182

tech-stack:
  added: []
  patterns:
    - "Bounded allowlisted trigger evaluation (WorkflowRule::ALLOWED_TRIGGERS) with declarative key-equality condition checks and zero dynamic eval()"
    - "Term-cycle idempotent renewal window notifications using entityKey renewal_window:{member_id}:{expiry_date}"
    - "Automated post-expiry active-to-inactive member status transition with immutable member_status_changed audit logging"

key-files:
  created:
    - app/Domain/Mail/Models/WorkflowRule.php
    - app/Domain/Mail/Services/WorkflowRuleEngine.php
    - database/seeders/WorkflowRuleSeeder.php
    - app/Console/Commands/ProcessMembershipMilestonesCommand.php
    - app/Livewire/Mail/WorkflowRuleManager.php
    - resources/views/livewire/mail/workflow-rule-manager.blade.php
    - tests/Feature/WorkflowRuleEngineTest.php
    - tests/Feature/WorkflowRuleManagerTest.php
  modified:
    - database/seeders/DatabaseSeeder.php
    - routes/console.php
    - routes/web.php

key-decisions:
  - "Restricted WorkflowRuleEngine::trigger strictly to the 9 allowlisted constants in WorkflowRule::ALLOWED_TRIGGERS and evaluated conditions via declarative key equality without eval() per MAIL-05, DEC-02, and T-08-10"
  - "Included the member's expiry_date in the deduplication entityKey (renewal_window:{member_id}:{expiry_date} and expired:{member_id}:{expiry_date}) so daily scheduler runs remain idempotent within a term while supporting future renewed terms"
  - "Transitioned past-expiry active members to 'inactive' during crm:process-membership-milestones with append-only member_status_changed audit records per STATUS-DICTIONARY.md Section 7.2 and DEC-11"
  - "Guarded /crm/{workspace}/settings/workflow-rules with workspace.access, can:platform_services.manage_settings, and ValidatesWorkspaceOnHydration, verifying bound email_template_id belongs to the same workspace per T-08-11"

patterns-established:
  - "Zero-eval workflow rule execution via WorkflowRuleEngine::trigger and daily scheduled milestone scans via crm:process-membership-milestones"

requirements-completed:
  - MAIL-05

coverage:
  - id: D1
    description: "Bounded zero-eval WorkflowRuleEngine, 9-trigger WorkflowRuleSeeder, and daily crm:process-membership-milestones command dispatching 30-day renewal window notices and transitioning expired members to inactive with audit logging"
    requirement: MAIL-05
    verification:
      - kind: integration
        ref: "tests/Feature/WorkflowRuleEngineTest.php#test_seeder_populates_all_nine_canonical_workflow_rules_linked_to_templates"
        status: pass
      - kind: integration
        ref: "tests/Feature/WorkflowRuleEngineTest.php#test_workflow_rule_engine_triggers_email_when_enabled_and_skips_when_disabled"
        status: pass
      - kind: integration
        ref: "tests/Feature/WorkflowRuleEngineTest.php#test_workflow_rule_engine_rejects_unknown_trigger_event_outside_allowlist"
        status: pass
      - kind: integration
        ref: "tests/Feature/WorkflowRuleEngineTest.php#test_process_membership_milestones_command_dispatches_renewal_window_and_transitions_expired_members_idempotently"
        status: pass
    human_judgment: false
  - id: D2
    description: "Livewire WorkflowRuleManager administrative UI at /crm/{workspace}/settings/workflow-rules allowing authorized managers to toggle rules, bind templates, and configure days_offset with immutable workflow_rule_updated audit logging and 403 tenancy/RBAC enforcement"
    requirement: MAIL-05
    verification:
      - kind: integration
        ref: "tests/Feature/WorkflowRuleManagerTest.php#test_workspace_manager_can_access_workflow_rule_manager_and_view_all_nine_rules"
        status: pass
      - kind: integration
        ref: "tests/Feature/WorkflowRuleManagerTest.php#test_toggling_and_configuring_workflow_rule_persists_changes_and_logs_audit_events"
        status: pass
      - kind: integration
        ref: "tests/Feature/WorkflowRuleManagerTest.php#test_unauthorized_staff_and_cross_workspace_managers_receive_403"
        status: pass
    human_judgment: false

duration: 7 min
completed: 2026-11-04
status: complete
---

# Phase 08 Plan 04: Bounded Workflow Rule Engine, Membership Milestone Scheduler & WorkflowRuleManager Summary

**Bounded zero-eval `WorkflowRuleEngine` for all 9 lifecycle triggers, daily `crm:process-membership-milestones` renewal/expiry scheduler, and Livewire `WorkflowRuleManager` administrative UI with immutable audit logging.**

## Performance

- **Duration:** 7 min
- **Started:** 2026-11-04T17:08:30Z
- **Completed:** 2026-11-04T17:15:30Z
- **Tasks:** 2
- **Files modified:** 11

## Accomplishments

- Created `WorkflowRule` model (`TenantEntityInterface`, `BelongsToTenant`) defining the 9 canonical allowlisted trigger constants (`lead.submitted`, `lead.missing_items`, `lead.rejected`, `lead.converted`, `member.portal_activated`, `id_card.ready_for_pickup`, `member.renewal_window_open`, `member.expired`, `member.change_request_outcome`).
- Created `WorkflowRuleSeeder` (and registered in `DatabaseSeeder`) seeding all 9 default workflow rules per workspace linked to their corresponding `EmailTemplate` (`days_offset = 30` for `member.renewal_window_open`, `is_enabled = true`).
- Implemented `WorkflowRuleEngine` enforcing `WorkflowRule::ALLOWED_TRIGGERS`, declarative condition matching without `eval()`, templated email queueing via `QueuedEmailDispatcher`, and daily workspace membership milestone scanning (`processWorkspaceMembershipMilestones`).
- Created `ProcessMembershipMilestonesCommand` (`crm:process-membership-milestones`) scheduled daily at `01:00` in `routes/console.php`, queueing 30-day renewal window notifications idempotently per term expiry cycle and transitioning past-expiry active members to `'inactive'` with `member_status_changed` audit logs and expiry emails.
- Built Livewire `WorkflowRuleManager` component and Blade view at `/crm/{workspace}/settings/workflow-rules` guarded by `workspace.access`, `can:platform_services.manage_settings`, and `ValidatesWorkspaceOnHydration`, recording `workflow_rule_updated` audit events on every rule toggle or configuration update.

## Task Commits

Each task was committed atomically:

1. **Task 1: Bounded WorkflowRule Model, WorkflowRuleEngine & Membership Expiry/Renewal Scheduler** - `85838fa` (feat)
2. **Task 2: Livewire WorkflowRuleManager Administrative Screen & Audit Logging** - `ea0bba5` (feat)

## Files Created/Modified

- `app/Domain/Mail/Models/WorkflowRule.php` - Tenant-scoped workflow rule model with 9 allowlisted trigger constants and `template()` relation.
- `app/Domain/Mail/Services/WorkflowRuleEngine.php` - Zero-eval workflow rule evaluator and daily membership renewal/expiry milestone processor.
- `database/seeders/WorkflowRuleSeeder.php` - Seeder populating all 9 canonical workflow rules per workspace linked to bilingual email templates.
- `database/seeders/DatabaseSeeder.php` - Registered `WorkflowRuleSeeder`.
- `app/Console/Commands/ProcessMembershipMilestonesCommand.php` - Artisan `crm:process-membership-milestones` command for daily renewal window and expiration processing.
- `routes/console.php` - Scheduled `crm:process-membership-milestones` daily at `01:00`.
- `app/Livewire/Mail/WorkflowRuleManager.php` - Livewire administrative manager for toggling workflow rules, binding workspace templates, and configuring `days_offset`.
- `resources/views/livewire/mail/workflow-rule-manager.blade.php` - Responsive Blade view listing all 9 workflow rules and inline configuration form.
- `routes/web.php` - Registered `/crm/{workspace}/settings/workflow-rules` route with `can:platform_services.manage_settings`.
- `tests/Feature/WorkflowRuleEngineTest.php` - Feature tests for 9-rule seeding, enabled/disabled rule evaluation, unknown trigger rejection, and idempotent daily renewal/expiry processing.
- `tests/Feature/WorkflowRuleManagerTest.php` - Feature tests for manager UI access, rule toggling, `days_offset` updates, `workflow_rule_updated` audit logs, and 403 tenancy/RBAC enforcement.

## Decisions Made

- Enforced strict allowlist validation against `WorkflowRule::ALLOWED_TRIGGERS` and declarative key-equality condition checks in `WorkflowRuleEngine` with zero dynamic code evaluation per DEC-02 and T-08-10.
- Used `DB::table('workflow_rules')` and `DB::table('members')` inside `WorkflowRuleEngine` so console/background commands can process workspace rules and member milestones without calling `withoutGlobalScopes` inside `App\Domain` (preserving `TenancyArchTest` compliance).
- Scoped renewal and expiry deduplication `entityKey` strings to include the member's `expiry_date` (`renewal_window:{member_id}:{expiry_date}` and `expired:{member_id}:{expiry_date}`) so repeated daily scheduler runs never double-send during the same term cycle.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - workflow rules are seeded automatically via `WorkflowRuleSeeder` and lazily ensured by `WorkflowRuleEngine` and `WorkflowRuleManager`.

## Next Phase Readiness

- All Phase 8 functional components (IMAP sync, threaded correspondence panel, bilingual templates, Horizon queued delivery monitor, and bounded workflow rules engine) are complete and ready for Plan 08-05 (Phase 8 End-to-End Integration & Lifecycle Verification).

## Self-Check: PASSED

- Verified all created and modified files exist on disk.
- Verified task commits `85838fa` and `ea0bba5` exist in git history.
- Verified `php artisan test tests/Feature/WorkflowRuleEngineTest.php tests/Feature/WorkflowRuleManagerTest.php tests/Arch/ModuleRegistryArchTest.php tests/Arch/TenancyArchTest.php` passes (12 tests, 75 assertions).

---
*Phase: 08-official-email-imap-templates-and-workflow-rules*
*Completed: 2026-11-04*
