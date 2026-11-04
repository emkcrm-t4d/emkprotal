<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\WorkflowRule;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowRuleSeeder extends Seeder
{
    /**
     * Run the database seeds for all workspaces.
     */
    public function run(): void
    {
        $workspaceIds = Workspace::query()->pluck('id')->all();

        if (! in_array('ws-emk-dhaka', $workspaceIds, true) && Workspace::query()->where('id', 'ws-emk-dhaka')->exists()) {
            $workspaceIds[] = 'ws-emk-dhaka';
        }

        foreach ($workspaceIds as $workspaceId) {
            self::seedForWorkspace((string) $workspaceId);
        }
    }

    /**
     * Ensure templates exist and seed all 9 canonical workflow rules for a workspace.
     */
    public static function seedForWorkspace(string $workspaceId): void
    {
        if (! Workspace::query()->where('id', $workspaceId)->exists()) {
            return;
        }

        if (DB::table('email_templates')->where('workspace_id', $workspaceId)->count() < 9) {
            EmailTemplateSeeder::seedForWorkspace($workspaceId);
        }

        foreach (self::canonicalRules() as $ruleData) {
            $templateId = DB::table('email_templates')
                ->where('workspace_id', $workspaceId)
                ->where('template_key', $ruleData['template_key'])
                ->value('id');

            $existing = DB::table('workflow_rules')
                ->where('workspace_id', $workspaceId)
                ->where('rule_key', $ruleData['rule_key'])
                ->first();

            if ($existing !== null) {
                if ($existing->email_template_id === null && $templateId !== null) {
                    DB::table('workflow_rules')
                        ->where('id', $existing->id)
                        ->update([
                            'email_template_id' => $templateId,
                            'updated_at' => now(),
                        ]);
                }

                continue;
            }

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

    /**
     * Return all 9 canonical workflow rules per MAIL-05 and DEC-02.
     *
     * @return list<array{
     *     rule_key: string,
     *     name: string,
     *     trigger_event: string,
     *     template_key: string,
     *     days_offset: int,
     *     conditions: array<string, mixed>
     * }>
     */
    public static function canonicalRules(): array
    {
        return [
            [
                'rule_key' => 'rule_lead_submitted',
                'name' => 'Application Receipt Auto-Confirmation',
                'trigger_event' => WorkflowRule::TRIGGER_LEAD_SUBMITTED,
                'template_key' => EmailTemplate::MILESTONE_APPLICATION_RECEIPT,
                'days_offset' => 0,
                'conditions' => [],
            ],
            [
                'rule_key' => 'rule_lead_missing_items',
                'name' => 'Missing / Resubmission Items Notification',
                'trigger_event' => WorkflowRule::TRIGGER_LEAD_MISSING_ITEMS,
                'template_key' => EmailTemplate::MILESTONE_MISSING_ITEMS,
                'days_offset' => 0,
                'conditions' => [],
            ],
            [
                'rule_key' => 'rule_lead_rejected',
                'name' => 'Application Rejection Notification',
                'trigger_event' => WorkflowRule::TRIGGER_LEAD_REJECTED,
                'template_key' => EmailTemplate::MILESTONE_REJECTION,
                'days_offset' => 0,
                'conditions' => [],
            ],
            [
                'rule_key' => 'rule_lead_converted',
                'name' => 'Membership Conversion & Invoice Dispatch',
                'trigger_event' => WorkflowRule::TRIGGER_LEAD_CONVERTED,
                'template_key' => EmailTemplate::MILESTONE_CONVERSION_CONFIRMATION,
                'days_offset' => 0,
                'conditions' => [],
            ],
            [
                'rule_key' => 'rule_portal_activated',
                'name' => 'Member Portal Account Activation Dispatch',
                'trigger_event' => WorkflowRule::TRIGGER_PORTAL_ACTIVATED,
                'template_key' => EmailTemplate::MILESTONE_PORTAL_ACTIVATION,
                'days_offset' => 0,
                'conditions' => [],
            ],
            [
                'rule_key' => 'rule_id_card_ready',
                'name' => 'ID Card Printed & Ready for Pickup Alert',
                'trigger_event' => WorkflowRule::TRIGGER_ID_CARD_READY,
                'template_key' => EmailTemplate::MILESTONE_ID_CARD_READY,
                'days_offset' => 0,
                'conditions' => [],
            ],
            [
                'rule_key' => 'rule_renewal_window_open',
                'name' => 'Upcoming Membership Renewal Window Reminder',
                'trigger_event' => WorkflowRule::TRIGGER_RENEWAL_WINDOW_OPEN,
                'template_key' => EmailTemplate::MILESTONE_RENEWAL_WINDOW,
                'days_offset' => 30,
                'conditions' => ['require_status' => 'active'],
            ],
            [
                'rule_key' => 'rule_member_expired',
                'name' => 'Membership Expiration & Inactive Transition Notice',
                'trigger_event' => WorkflowRule::TRIGGER_MEMBER_EXPIRED,
                'template_key' => EmailTemplate::MILESTONE_EXPIRY,
                'days_offset' => 0,
                'conditions' => [],
            ],
            [
                'rule_key' => 'rule_change_request_outcome',
                'name' => 'Profile / Email Change Request Outcome Notification',
                'trigger_event' => WorkflowRule::TRIGGER_REQUEST_OUTCOME,
                'template_key' => EmailTemplate::MILESTONE_REQUEST_OUTCOME,
                'days_offset' => 0,
                'conditions' => [],
            ],
        ];
    }
}
