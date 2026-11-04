<?php

declare(strict_types=1);

namespace App\Domain\Mail\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\WorkflowRule;
use App\Domain\Members\Models\Member;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkflowRuleEngine
{
    public function __construct(
        protected QueuedEmailDispatcher $dispatcher
    ) {
    }

    /**
     * Evaluate a bounded workflow rule for a workspace and trigger event, and dispatch
     * the linked EmailTemplate via QueuedEmailDispatcher if enabled.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function trigger(
        string $workspaceId,
        string $triggerEvent,
        string $recipientEmail,
        array $variables = [],
        ?int $leadId = null,
        ?int $memberId = null,
        ?string $entityKey = null
    ): ?EmailMessage {
        if (! in_array($triggerEvent, WorkflowRule::ALLOWED_TRIGGERS, true)) {
            throw new InvalidArgumentException("Unsupported workflow trigger event: {$triggerEvent}");
        }

        $ruleRow = DB::table('workflow_rules')
            ->where('workspace_id', $workspaceId)
            ->where('trigger_event', $triggerEvent)
            ->where('is_enabled', true)
            ->first();

        if ($ruleRow === null || $ruleRow->email_template_id === null) {
            return null;
        }

        $templateRow = DB::table('email_templates')
            ->where('id', $ruleRow->email_template_id)
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->first();

        if ($templateRow === null) {
            return null;
        }

        $conditions = is_string($ruleRow->conditions)
            ? (json_decode($ruleRow->conditions, true) ?? [])
            : (is_array($ruleRow->conditions) ? $ruleRow->conditions : []);

        if (! $this->evaluateConditions($conditions, $variables)) {
            return null;
        }

        $resolvedEntityKey = $entityKey ?? "{$triggerEvent}:" . ($memberId ?? $leadId ?? strtolower(trim($recipientEmail)));

        return $this->dispatcher->dispatchTemplate(
            $workspaceId,
            (string) $templateRow->template_key,
            $recipientEmail,
            $variables,
            $leadId,
            $memberId,
            $resolvedEntityKey
        );
    }

    /**
     * Process daily membership renewal window and post-expiry status transition milestones
     * for a workspace per STATUS-DICTIONARY.md Section 7.2 and MAIL-05.
     *
     * @return array{renewal_notices_queued: int, expired_transitions: int}
     */
    public function processWorkspaceMembershipMilestones(
        string $workspaceId,
        ?Carbon $referenceDate = null
    ): array {
        $today = ($referenceDate ?? now())->copy()->startOfDay();
        $this->ensureDefaultRulesForWorkspace($workspaceId);

        $workspaceName = (string) (DB::table('workspaces')->where('id', $workspaceId)->value('name') ?? $workspaceId);

        $renewalNoticesQueued = 0;
        $expiredTransitions = 0;

        // 1. Evaluate Renewal Window rule (default 30 days prior to expiry_date)
        $renewalRule = DB::table('workflow_rules')
            ->where('workspace_id', $workspaceId)
            ->where('trigger_event', WorkflowRule::TRIGGER_RENEWAL_WINDOW_OPEN)
            ->first();

        if ($renewalRule !== null && (bool) $renewalRule->is_enabled) {
            $daysOffset = max(1, (int) ($renewalRule->days_offset ?: 30));
            $windowEnd = $today->copy()->addDays($daysOffset);

            $expiringMembers = DB::table('members')
                ->where('workspace_id', $workspaceId)
                ->where('status', 'active')
                ->whereDate('expiry_date', '>=', $today->toDateString())
                ->whereDate('expiry_date', '<=', $windowEnd->toDateString())
                ->get();

            foreach ($expiringMembers as $row) {
                $expiryDateStr = Carbon::parse((string) $row->expiry_date)->format('Y-m-d');
                $dedupEntityKey = "renewal_window:{$row->id}:{$expiryDateStr}";

                $templateKey = EmailTemplate::MILESTONE_RENEWAL_WINDOW;
                if ($renewalRule->email_template_id !== null) {
                    $boundKey = DB::table('email_templates')
                        ->where('id', $renewalRule->email_template_id)
                        ->value('template_key');
                    if (is_string($boundKey) && $boundKey !== '') {
                        $templateKey = $boundKey;
                    }
                }

                $dedupKey = $this->dispatcher->buildDeduplicationKey(
                    $workspaceId,
                    $templateKey,
                    (string) $row->email,
                    $dedupEntityKey
                );

                $alreadyQueuedOrSent = DB::table('email_messages')
                    ->where('deduplication_key', $dedupKey)
                    ->whereIn('status', ['queued', 'sent'])
                    ->exists();

                if ($alreadyQueuedOrSent) {
                    continue;
                }

                $fullName = $this->formatMemberFullName($row);
                $feeBdt = '1,000.00';
                if ($row->membership_term_id !== null) {
                    $termFee = DB::table('membership_terms')->where('id', $row->membership_term_id)->value('fee_bdt');
                    if ($termFee !== null) {
                        $feeBdt = number_format((float) $termFee, 2);
                    }
                }

                $dispatched = $this->trigger(
                    $workspaceId,
                    WorkflowRule::TRIGGER_RENEWAL_WINDOW_OPEN,
                    (string) $row->email,
                    [
                        'member_name' => $fullName,
                        'member_id' => (string) $row->member_id,
                        'expiry_date' => $expiryDateStr,
                        'workspace_name' => $workspaceName,
                        'amount_bdt' => $feeBdt,
                        'action_url' => (string) config('app.url', 'https://hub.emkcenter.org') . '/portal',
                        'status' => (string) $row->status,
                    ],
                    $row->lead_id !== null ? (int) $row->lead_id : null,
                    (int) $row->id,
                    $dedupEntityKey
                );

                if ($dispatched !== null) {
                    $renewalNoticesQueued++;
                }
            }
        }

        // 2. Transition expired active members to 'inactive' and dispatch expiry notification
        $expiredMembers = DB::table('members')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->whereDate('expiry_date', '<', $today->toDateString())
            ->get();

        foreach ($expiredMembers as $row) {
            $expiryDateStr = Carbon::parse((string) $row->expiry_date)->format('Y-m-d');

            DB::table('members')
                ->where('id', $row->id)
                ->update([
                    'status' => 'inactive',
                    'updated_at' => now(),
                ]);

            AuditLog::create([
                'workspace_id' => $workspaceId,
                'actor_id' => null,
                'actor_guard' => 'system',
                'event_type' => AuditLog::EVENT_MEMBER_STATUS_CHANGED,
                'auditable_type' => Member::class,
                'auditable_id' => (int) $row->id,
                'old_values' => ['status' => 'active'],
                'new_values' => [
                    'status' => 'inactive',
                    'expiry_date' => $expiryDateStr,
                ],
                'reason' => 'Automated membership expiration milestone transition.',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/Scheduler',
                'created_at' => now(),
            ]);

            $fullName = $this->formatMemberFullName($row);

            $this->trigger(
                $workspaceId,
                WorkflowRule::TRIGGER_MEMBER_EXPIRED,
                (string) $row->email,
                [
                    'member_name' => $fullName,
                    'member_id' => (string) $row->member_id,
                    'expiry_date' => $expiryDateStr,
                    'workspace_name' => $workspaceName,
                    'action_url' => (string) config('app.url', 'https://hub.emkcenter.org') . '/portal',
                    'status' => 'inactive',
                ],
                $row->lead_id !== null ? (int) $row->lead_id : null,
                (int) $row->id,
                "expired:{$row->id}:{$expiryDateStr}"
            );

            $expiredTransitions++;
        }

        return [
            'renewal_notices_queued' => $renewalNoticesQueued,
            'expired_transitions' => $expiredTransitions,
        ];
    }

    /**
     * Evaluate declarative key-equality conditions strictly without eval() per DEC-02 and T-08-10.
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<string, scalar|null>  $variables
     */
    protected function evaluateConditions(array $conditions, array $variables): bool
    {
        if ($conditions === []) {
            return true;
        }

        if (isset($conditions['require_status']) && isset($variables['status'])) {
            if ((string) $variables['status'] !== (string) $conditions['require_status']) {
                return false;
            }
        }

        foreach ($conditions as $key => $expectedValue) {
            if ($key === 'require_status') {
                continue;
            }

            if (array_key_exists($key, $variables) && (string) $variables[$key] !== (string) $expectedValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure canonical templates and workflow rules exist for a workspace.
     */
    protected function ensureDefaultRulesForWorkspace(string $workspaceId): void
    {
        if (! DB::table('workspaces')->where('id', $workspaceId)->exists()) {
            return;
        }

        $existingCount = DB::table('workflow_rules')->where('workspace_id', $workspaceId)->count();
        if ($existingCount >= 9) {
            return;
        }

        $defaultRules = [
            ['rule_key' => 'rule_lead_submitted', 'name' => 'Application Receipt Auto-Confirmation', 'trigger_event' => WorkflowRule::TRIGGER_LEAD_SUBMITTED, 'template_key' => EmailTemplate::MILESTONE_APPLICATION_RECEIPT, 'days_offset' => 0, 'conditions' => []],
            ['rule_key' => 'rule_lead_missing_items', 'name' => 'Missing / Resubmission Items Notification', 'trigger_event' => WorkflowRule::TRIGGER_LEAD_MISSING_ITEMS, 'template_key' => EmailTemplate::MILESTONE_MISSING_ITEMS, 'days_offset' => 0, 'conditions' => []],
            ['rule_key' => 'rule_lead_rejected', 'name' => 'Application Rejection Notification', 'trigger_event' => WorkflowRule::TRIGGER_LEAD_REJECTED, 'template_key' => EmailTemplate::MILESTONE_REJECTION, 'days_offset' => 0, 'conditions' => []],
            ['rule_key' => 'rule_lead_converted', 'name' => 'Membership Conversion & Invoice Dispatch', 'trigger_event' => WorkflowRule::TRIGGER_LEAD_CONVERTED, 'template_key' => EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION, 'days_offset' => 0, 'conditions' => []],
            ['rule_key' => 'rule_portal_activated', 'name' => 'Member Portal Account Activation Dispatch', 'trigger_event' => WorkflowRule::TRIGGER_PORTAL_ACTIVATED, 'template_key' => EmailTemplate::MILESTONE_PORTAL_ACTIVATION, 'days_offset' => 0, 'conditions' => []],
            ['rule_key' => 'rule_id_card_ready', 'name' => 'ID Card Printed & Ready for Pickup Alert', 'trigger_event' => WorkflowRule::TRIGGER_ID_CARD_READY, 'template_key' => EmailTemplate::MILESTONE_ID_CARD_READY, 'days_offset' => 0, 'conditions' => []],
            ['rule_key' => 'rule_renewal_window_open', 'name' => 'Upcoming Membership Renewal Window Reminder', 'trigger_event' => WorkflowRule::TRIGGER_RENEWAL_WINDOW_OPEN, 'template_key' => EmailTemplate::MILESTONE_RENEWAL_WINDOW, 'days_offset' => 30, 'conditions' => ['require_status' => 'active']],
            ['rule_key' => 'rule_member_expired', 'name' => 'Membership Expiration & Inactive Transition Notice', 'trigger_event' => WorkflowRule::TRIGGER_MEMBER_EXPIRED, 'template_key' => EmailTemplate::MILESTONE_EXPIRY, 'days_offset' => 0, 'conditions' => []],
            ['rule_key' => 'rule_change_request_outcome', 'name' => 'Profile / Email Change Request Outcome Notification', 'trigger_event' => WorkflowRule::TRIGGER_REQUEST_OUTCOME, 'template_key' => EmailTemplate::MILESTONE_REQUEST_OUTCOME, 'days_offset' => 0, 'conditions' => []],
        ];

        foreach ($defaultRules as $ruleData) {
            $templateId = DB::table('email_templates')
                ->where('workspace_id', $workspaceId)
                ->where('template_key', $ruleData['template_key'])
                ->value('id');

            if ($templateId === null) {
                $templateId = DB::table('email_templates')->insertGetId([
                    'workspace_id' => $workspaceId,
                    'template_key' => $ruleData['template_key'],
                    'name' => $ruleData['name'],
                    'milestone_type' => $ruleData['template_key'],
                    'subject_en' => "{$ruleData['name']} — {{workspace_name}}",
                    'body_en' => '<p>Dear {{applicant_name}}{{member_name}},</p><p>Member ID: {{member_id}} | Expiry: {{expiry_date}} | {{reason}}</p>',
                    'subject_bn' => "{{workspace_name}} — {$ruleData['name']}",
                    'body_bn' => '<p>প্রিয় {{applicant_name}}{{member_name}},</p><p>মেম্বার আইডি: {{member_id}} | মেয়াদ: {{expiry_date}}</p>',
                    'allowed_variables' => json_encode(EmailTemplateRenderer::ALLOWED_PLACEHOLDERS),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $exists = DB::table('workflow_rules')
                ->where('workspace_id', $workspaceId)
                ->where('rule_key', $ruleData['rule_key'])
                ->exists();

            if (! $exists) {
                DB::table('workflow_rules')->insert([
                    'workspace_id' => $workspaceId,
                    'rule_key' => $ruleData['rule_key'],
                    'name' => $ruleData['name'],
                    'trigger_event' => $ruleData['trigger_event'],
                    'email_template_id' => $templateId,
                    'days_offset' => $ruleData['days_offset'],
                    'is_enabled' => true,
                    'conditions' => json_encode($ruleData['conditions']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Format full member name from raw DB row.
     */
    private function formatMemberFullName(object $row): string
    {
        $middle = ! empty($row->middle_name) ? $row->middle_name . ' ' : '';

        return trim(($row->first_name ?? '') . ' ' . $middle . ($row->last_name ?? ''));
    }
}
