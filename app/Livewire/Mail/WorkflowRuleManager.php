<?php

declare(strict_types=1);

namespace App\Livewire\Mail;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\WorkflowRule;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\WorkflowRuleSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class WorkflowRuleManager extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';

    public ?int $editingRuleId = null;

    public int $email_template_id = 0;

    public int $days_offset = 0;

    public bool $is_enabled = true;

    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;
        $this->authorizeAccess();

        if (EmailTemplate::query()->where('workspace_id', $this->workspace)->count() === 0) {
            EmailTemplateSeeder::seedForWorkspace($this->workspace);
        }

        if (WorkflowRule::query()->where('workspace_id', $this->workspace)->count() < 9) {
            WorkflowRuleSeeder::seedForWorkspace($this->workspace);
        }
    }

    /**
     * Assert managerial authorization and workspace tenancy access per T-08-11.
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (! $user) {
            App::abort(401, 'Unauthenticated.');
        }

        $canManage = $user->can('platform_services.manage_settings')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (! $canManage) {
            App::abort(403, 'Unauthorized: platform_services.manage_settings permission required.');
        }

        if (! $user->hasRole(['superadmin', 'global_admin']) && (string) $this->workspace !== (string) $user->workspace_id) {
            App::abort(403, 'Unauthorized workspace access.');
        }
    }

    /**
     * Toggle a workflow rule's enabled status and record an immutable audit event.
     */
    public function toggleRule(int $ruleId): void
    {
        $this->authorizeAccess();

        $rule = WorkflowRule::query()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($ruleId);

        $previousEnabled = (bool) $rule->is_enabled;
        $newEnabled = ! $previousEnabled;
        $user = Auth::user();

        $rule->update([
            'is_enabled' => $newEnabled,
            'updated_by_user_id' => $user?->id,
        ]);

        AuditLog::create([
            'workspace_id' => $this->workspace,
            'actor_id' => $user?->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_WORKFLOW_RULE_UPDATED,
            'auditable_type' => WorkflowRule::class,
            'auditable_id' => $rule->id,
            'old_values' => ['is_enabled' => $previousEnabled],
            'new_values' => ['is_enabled' => $newEnabled],
            'reason' => "Toggled workflow rule [{$rule->rule_key}] enabled status.",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'Livewire/WorkflowRuleManager',
            'created_at' => now(),
        ]);

        session()->flash('status', 'Workflow rule status updated.');
    }

    /**
     * Open configuration panel for a specific workspace workflow rule.
     */
    public function editRule(int $ruleId): void
    {
        $this->authorizeAccess();
        $this->resetValidation();

        $rule = WorkflowRule::query()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($ruleId);

        $this->editingRuleId = (int) $rule->id;
        $this->email_template_id = (int) ($rule->email_template_id ?? 0);
        $this->days_offset = (int) $rule->days_offset;
        $this->is_enabled = (bool) $rule->is_enabled;
    }

    /**
     * Close the configuration panel.
     */
    public function cancelEdit(): void
    {
        $this->editingRuleId = null;
        $this->resetValidation();
    }

    /**
     * Validate and save updated workflow rule configuration with immutable audit logging.
     */
    public function saveRule(): void
    {
        $this->authorizeAccess();

        if (! $this->editingRuleId) {
            return;
        }

        $workspaceId = $this->workspace;

        $this->validate([
            'email_template_id' => [
                'required',
                'integer',
                Rule::exists('email_templates', 'id')->where(function ($query) use ($workspaceId) {
                    $query->where('workspace_id', $workspaceId);
                }),
            ],
            'days_offset' => ['required', 'integer', 'min:0', 'max:365'],
            'is_enabled' => ['boolean'],
        ]);

        $rule = WorkflowRule::query()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($this->editingRuleId);

        $oldValues = [
            'email_template_id' => (int) ($rule->email_template_id ?? 0),
            'days_offset' => (int) $rule->days_offset,
            'is_enabled' => (bool) $rule->is_enabled,
        ];

        $user = Auth::user();

        $rule->update([
            'email_template_id' => $this->email_template_id,
            'days_offset' => $this->days_offset,
            'is_enabled' => (bool) $this->is_enabled,
            'updated_by_user_id' => $user?->id,
        ]);

        $newValues = [
            'email_template_id' => (int) $rule->email_template_id,
            'days_offset' => (int) $rule->days_offset,
            'is_enabled' => (bool) $rule->is_enabled,
        ];

        AuditLog::create([
            'workspace_id' => $this->workspace,
            'actor_id' => $user?->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_WORKFLOW_RULE_UPDATED,
            'auditable_type' => WorkflowRule::class,
            'auditable_id' => $rule->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => "Updated workflow rule configuration [{$rule->rule_key}].",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'Livewire/WorkflowRuleManager',
            'created_at' => now(),
        ]);

        $this->editingRuleId = null;
        session()->flash('status', 'Workflow rule updated successfully.');
    }

    public function render(): View
    {
        $rules = WorkflowRule::query()
            ->with('template')
            ->where('workspace_id', $this->workspace)
            ->orderBy('id', 'asc')
            ->get();

        $templates = EmailTemplate::query()
            ->where('workspace_id', $this->workspace)
            ->orderBy('id', 'asc')
            ->get();

        return view('livewire.mail.workflow-rule-manager', [
            'rules' => $rules,
            'templates' => $templates,
        ]);
    }
}
