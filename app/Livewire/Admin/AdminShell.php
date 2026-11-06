<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Domain\Platform\Registry\ModuleRegistryInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AdminShell extends Component
{
    /**
     * Active workspace details per DEC-01 (D-01).
     *
     * @var array{id: string, name: string, is_active: bool}
     */
    public array $workspace = [
        'id' => 'ws-emk-dhaka',
        'name' => 'EMK Center Dhaka',
        'is_active' => true,
    ];

    /**
     * Currently active module key for navigation highlighting.
     */
    public ?string $activeModule = null;

    /**
     * Sidebar collapse/toggle state.
     */
    public bool $isSidebarOpen = false;

    public function mount(ModuleRegistryInterface $registry, ?string $activeModule = null): void
    {
        $this->activeModule = $activeModule;

        $user = Auth::guard('web')->user();
        if ($user && !empty($user->workspace_id)) {
            $this->workspace['id'] = (string) $user->workspace_id;

            $workspaceRow = DB::table('workspaces')
                ->where('id', $user->workspace_id)
                ->first();

            if ($workspaceRow) {
                $this->workspace['name'] = (string) $workspaceRow->name;
                $this->workspace['is_active'] = (bool) $workspaceRow->is_active;
            }
        }
    }

    public function toggleSidebar(): void
    {
        $this->isSidebarOpen = !$this->isSidebarOpen;
    }

    public function closeSidebar(): void
    {
        $this->isSidebarOpen = false;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }

    public function render(ModuleRegistryInterface $registry): View
    {
        $workspaceId = $this->workspace['id'] ?? 'ws-emk-dhaka';
        $allModules = $registry->getModules();

        // Filter modules by workspace enablement per MODULE-REGISTRY.md
        $modules = array_filter($allModules, function ($module) use ($registry, $workspaceId) {
            return $registry->isModuleEnabledForWorkspace($module->key, $workspaceId);
        });

        // Custom sequence and display names requested by user
        $sequenceOrder = [
            'leads' => 1,
            'members' => 2,
            'invoices' => 3,
            'dashboard_reports' => 4,
            'forms' => 5,
            'member_portal' => 6,
            'migration' => 7,
            'platform_services' => 8,
        ];

        $displayNames = [
            'leads' => 'Lead and Applicant',
            'members' => 'Member Profiles & ID',
            'invoices' => 'Billing & Fee Records',
            'dashboard_reports' => 'Operational & Mission',
            'platform_services' => 'Settings',
            'forms' => 'Form Builder',
            'member_portal' => 'Member Portal',
            'migration' => 'Legacy Zoho Ingestion',
        ];

        usort($modules, function ($a, $b) use ($sequenceOrder) {
            $orderA = $sequenceOrder[$a->key] ?? 99;
            $orderB = $sequenceOrder[$b->key] ?? 99;
            return $orderA <=> $orderB;
        });

        // 8 Platform & Administration Submenus under Settings
        $settingsSubmenus = [
            [
                'title' => 'System Parameters',
                'route' => route('crm.settings.index', $workspaceId),
                'active' => request()->routeIs('crm.settings.index') || request()->is("crm/{$workspaceId}/settings"),
                'key' => 'parameters',
            ],
            [
                'title' => 'Membership Terms',
                'route' => route('crm.terms.index', $workspaceId),
                'active' => request()->routeIs('crm.terms.index') || request()->is("crm/{$workspaceId}/settings/terms*"),
                'key' => 'terms',
            ],
            [
                'title' => 'Staff & Delegations',
                'route' => route('crm.staff.index', $workspaceId),
                'active' => request()->routeIs('crm.staff.index') || request()->routeIs('crm.staff.delegation') || request()->is("crm/{$workspaceId}/staff*"),
                'key' => 'staff',
            ],
            [
                'title' => 'Email Templates',
                'route' => route('crm.email_templates.index', $workspaceId),
                'active' => request()->routeIs('crm.email_templates.index') || request()->is("crm/{$workspaceId}/settings/email-templates*"),
                'key' => 'templates',
            ],
            [
                'title' => 'Delivery Monitor',
                'route' => route('crm.email_delivery.index', $workspaceId),
                'active' => request()->routeIs('crm.email_delivery.index') || request()->is("crm/{$workspaceId}/settings/email-delivery*"),
                'key' => 'delivery',
            ],
            [
                'title' => 'Workflow Rules',
                'route' => route('crm.workflow_rules.index', $workspaceId),
                'active' => request()->routeIs('crm.workflow_rules.index') || request()->is("crm/{$workspaceId}/settings/workflow-rules*"),
                'key' => 'workflow',
            ],
            [
                'title' => 'Backups & Health',
                'route' => route('crm.backups.index', $workspaceId),
                'active' => request()->routeIs('crm.backups.index') || request()->is("crm/{$workspaceId}/settings/backups*"),
                'key' => 'backups',
            ],
            [
                'title' => 'Audit Logs',
                'route' => route('crm.audit_logs.index', $workspaceId),
                'active' => request()->routeIs('crm.audit_logs.index') || request()->is("crm/{$workspaceId}/audit-logs*"),
                'key' => 'audit',
            ],
        ];

        $user = Auth::guard('web')->user();

        return view('livewire.admin.admin-shell', [
            'workspace' => $this->workspace,
            'modules' => $modules,
            'displayNames' => $displayNames,
            'settingsSubmenus' => $settingsSubmenus,
            'user' => $user,
        ]);
    }
}
