<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\WorkflowRule;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Mail\WorkflowRuleManager;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowRuleManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $unauthorizedStaff;
    protected User $otherWorkspaceManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Workspace Manager',
            'email' => 'manager.wf@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        $this->unauthorizedStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Front Desk Staff',
            'email' => 'staff.wf@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->unauthorizedStaff->assignRole('delegated_staff');
        $this->unauthorizedStaff->givePermissionTo(['leads.view']);

        $this->otherWorkspaceManager = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Manager',
            'email' => 'ctg.manager.wf@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->otherWorkspaceManager->assignRole('workspace_manager');
    }

    public function test_workspace_manager_can_access_workflow_rule_manager_and_view_all_nine_rules(): void
    {
        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/settings/workflow-rules');

        $response->assertStatus(200);
        $response->assertSee('Automated Lifecycle Workflow Rules');

        foreach (WorkflowRule::ALLOWED_TRIGGERS as $triggerEvent) {
            $response->assertSee($triggerEvent);
        }
    }

    public function test_toggling_and_configuring_workflow_rule_persists_changes_and_logs_audit_events(): void
    {
        $renewalRule = WorkflowRule::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->where('trigger_event', WorkflowRule::TRIGGER_RENEWAL_WINDOW_OPEN)
            ->firstOrFail();

        $this->assertTrue($renewalRule->is_enabled);
        $this->assertSame(30, $renewalRule->days_offset);

        // 1. Toggle rule off
        Livewire::actingAs($this->manager)
            ->test(WorkflowRuleManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('toggleRule', $renewalRule->id)
            ->assertHasNoErrors();

        $this->assertFalse($renewalRule->fresh()->is_enabled);

        $toggleAudit = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_WORKFLOW_RULE_UPDATED)
            ->where('auditable_type', WorkflowRule::class)
            ->where('auditable_id', $renewalRule->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($toggleAudit);
        $this->assertSame(true, $toggleAudit->old_values['is_enabled'] ?? null);
        $this->assertSame(false, $toggleAudit->new_values['is_enabled'] ?? null);

        // 2. Edit rule and update days_offset from 30 to 45 and re-enable
        Livewire::actingAs($this->manager)
            ->test(WorkflowRuleManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('editRule', $renewalRule->id)
            ->set('days_offset', 45)
            ->set('is_enabled', true)
            ->call('saveRule')
            ->assertHasNoErrors();

        $updatedRule = $renewalRule->fresh();
        $this->assertSame(45, $updatedRule->days_offset);
        $this->assertTrue($updatedRule->is_enabled);

        $saveAudit = AuditLog::withoutGlobalScopes()
            ->where('event_type', AuditLog::EVENT_WORKFLOW_RULE_UPDATED)
            ->where('auditable_type', WorkflowRule::class)
            ->where('auditable_id', $renewalRule->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($saveAudit);
        $this->assertSame(30, $saveAudit->old_values['days_offset'] ?? null);
        $this->assertSame(45, $saveAudit->new_values['days_offset'] ?? null);
    }

    public function test_unauthorized_staff_and_cross_workspace_managers_receive_403(): void
    {
        $this->actingAs($this->unauthorizedStaff)
            ->get('/crm/ws-emk-dhaka/settings/workflow-rules')
            ->assertStatus(403);

        $this->actingAs($this->otherWorkspaceManager)
            ->get('/crm/ws-emk-dhaka/settings/workflow-rules')
            ->assertStatus(403);
    }
}
