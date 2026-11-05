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
            'platform_services' => 5,
            'forms' => 6,
            'member_portal' => 7,
            'migration' => 8,
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

        $user = Auth::guard('web')->user();

        return view('livewire.admin.admin-shell', [
            'workspace' => $this->workspace,
            'modules' => $modules,
            'displayNames' => $displayNames,
            'user' => $user,
        ]);
    }
}
