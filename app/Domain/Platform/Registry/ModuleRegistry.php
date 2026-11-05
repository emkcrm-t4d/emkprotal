<?php

declare(strict_types=1);

namespace App\Domain\Platform\Registry;

class ModuleRegistry implements ModuleRegistryInterface
{
    /**
     * Static compilation of core modules.
     * Zero dynamic code evaluation per DEC-02 (D-02).
     *
     * @var array<string, ModuleDefinition>
     */
    protected array $modules = [];

    /**
     * Flat index of declared actions for O(1) lookup.
     *
     * @var array<string, ActionDefinition>
     */
    protected array $actions = [];

    /**
     * In-memory workspace module enablement overrides (useful for testing and runtime toggles).
     *
     * @var array<string, array<string, bool>>
     */
    protected array $workspaceOverrides = [];

    /**
     * The 8 delegable operational actions permitted for Workspace Managers to grant to Staff.
     */
    public const DELEGABLE_ACTIONS = [
        'forms.create',
        'forms.edit',
        'members.edit',
        'members.export_csv',
        'members.export_excel',
        'invoices.export',
        'dashboard_reports.generate_reports',
        'dashboard_reports.export_metrics',
    ];

    /**
     * The 18 managerial non-delegable actions locked to prevent staff privilege escalation per T-01-07.
     */
    public const STRICTLY_NON_DELEGABLE = [
        'forms.publish',
        'forms.archive',
        'leads.delete_soft',
        'leads.verify_payment',
        'leads.approve',
        'leads.reject',
        'leads.convert',
        'leads.reverse_conversion',
        'members.restrict',
        'members.revoke',
        'invoices.generate',
        'invoices.verify_payment',
        'platform_services.manage_settings',
        'platform_services.manage_users',
        'platform_services.delegate_roles',
        'platform_services.view_audit_logs',
        'migration.import_zoho',
        'migration.export_zoho',
    ];

    public function __construct()
    {
        $this->compileStaticRegistry();
    }

    /**
     * Statically compile all 8 core modules and declared actions without dynamic evaluation.
     */
    protected function compileStaticRegistry(): void
    {
        // 1. forms
        $this->registerModule(new ModuleDefinition(
            key: 'forms',
            name: 'Form Builder & Public Form Management',
            domainNamespace: 'App\Domain\Forms',
            description: 'Dynamic form builder, public endpoints, and draft submission triage.',
            actions: [
                'forms.view' => new ActionDefinition('forms.view', 'forms', 'View Forms', 'View form definitions and templates', false, false),
                'forms.create' => new ActionDefinition('forms.create', 'forms', 'Create Form', 'Design new application forms', true, false),
                'forms.edit' => new ActionDefinition('forms.edit', 'forms', 'Edit Form', 'Modify form layouts and field options', true, false),
                'forms.publish' => new ActionDefinition('forms.publish', 'forms', 'Publish Form', 'Deploy form to live public URL', false, true),
                'forms.archive' => new ActionDefinition('forms.archive', 'forms', 'Archive Form', 'Deactivate form from public access', false, true),
                'forms.view_entries' => new ActionDefinition('forms.view_entries', 'forms', 'View Entries', 'Inspect raw unmoderated form submissions', false, false),
            ],
            defaultRoute: 'crm.forms.index',
        ));

        // 2. leads
        $this->registerModule(new ModuleDefinition(
            key: 'leads',
            name: 'Lead Lifecycle & Tri-State Verification',
            domainNamespace: 'App\Domain\Leads',
            description: 'Applicant profile review, tri-state verification streams, and member conversion.',
            actions: [
                'leads.view' => new ActionDefinition('leads.view', 'leads', 'View Leads', 'Search and view lead profiles', false, false),
                'leads.create' => new ActionDefinition('leads.create', 'leads', 'Create Lead', 'Manually input walk-in application', false, false),
                'leads.edit' => new ActionDefinition('leads.edit', 'leads', 'Edit Lead', 'Update non-identity lead details', false, false),
                'leads.delete_soft' => new ActionDefinition('leads.delete_soft', 'leads', 'Soft Delete Lead', 'Soft delete invalid lead with audit trail', false, true),
                'leads.verify_payment' => new ActionDefinition('leads.verify_payment', 'leads', 'Verify Payment', 'Verify manual bKash TxID per DEC-05', false, true),
                'leads.verify_documents' => new ActionDefinition('leads.verify_documents', 'leads', 'Verify Documents', 'Verify identity and youth consent documents per DEC-09', false, false),
                'leads.verify_photo' => new ActionDefinition('leads.verify_photo', 'leads', 'Verify Photo', 'Verify studio passport photo quality checklist', false, false),
                'leads.communicate' => new ActionDefinition('leads.communicate', 'leads', 'Communicate Lead', 'Send official notifications to applicant', false, false),
                'leads.approve' => new ActionDefinition('leads.approve', 'leads', 'Approve Lead', 'Issue formal admission approval', false, true),
                'leads.reject' => new ActionDefinition('leads.reject', 'leads', 'Reject Lead', 'Issue formal application rejection', false, true),
                'leads.convert' => new ActionDefinition('leads.convert', 'leads', 'Convert Lead', 'Execute 10-step atomic Lead-to-Member conversion per DEC-03', false, true),
                'leads.reverse_conversion' => new ActionDefinition('leads.reverse_conversion', 'leads', 'Reverse Conversion', 'Roll back conversion within 30 days per DEC-04', false, true),
            ],
            defaultRoute: 'crm.leads',
        ));

        // 3. members
        $this->registerModule(new ModuleDefinition(
            key: 'members',
            name: 'Member Profiles & ID Cards',
            domainNamespace: 'App\Domain\Members',
            description: 'Member master records, physical plastic badge printing, and status lifecycle.',
            actions: [
                'members.view' => new ActionDefinition('members.view', 'members', 'View Members', 'Search and inspect member master profiles', false, false),
                'members.edit' => new ActionDefinition('members.edit', 'members', 'Edit Member', 'Update non-identity contact and address records', true, false),
                'members.restrict' => new ActionDefinition('members.restrict', 'members', 'Restrict Member', 'Temporarily suspend member privileges', false, true),
                'members.revoke' => new ActionDefinition('members.revoke', 'members', 'Revoke Member', 'Permanently revoke membership upon directive', false, true),
                'members.export_csv' => new ActionDefinition('members.export_csv', 'members', 'Export Members CSV', 'Export workspace members to CSV', true, false),
                'members.export_excel' => new ActionDefinition('members.export_excel', 'members', 'Export Members Excel', 'Export workspace members to Excel', true, false),
                'members.manage_cards' => new ActionDefinition('members.manage_cards', 'members', 'Manage ID Cards', 'Advance badge lifecycle (Printing, Ready, Delivered)', false, false),
            ],
            defaultRoute: 'crm.members',
        ));

        // 4. member_portal
        $this->registerModule(new ModuleDefinition(
            key: 'member_portal',
            name: 'Read-Only Member Portal',
            domainNamespace: 'App\Domain\Portal',
            description: 'Self-service read-only portal, change requests, and dedicated email update flows.',
            actions: [
                'member_portal.view_profile' => new ActionDefinition('member_portal.view_profile', 'member_portal', 'View Portal Profile', 'View personal member profile and terms per DEC-06', false, false),
                'member_portal.request_change' => new ActionDefinition('member_portal.request_change', 'member_portal', 'Request Profile Change', 'Submit change request for staff review', false, false),
                'member_portal.request_email_change' => new ActionDefinition('member_portal.request_email_change', 'member_portal', 'Request Email Change', 'Submit dedicated email change request per DEC-07', false, false),
                'member_portal.request_renewal' => new ActionDefinition('member_portal.request_renewal', 'member_portal', 'Request Renewal', 'Submit term renewal application and payment', false, false),
            ],
            defaultRoute: 'portal.profile',
        ));

        // 5. invoices
        $this->registerModule(new ModuleDefinition(
            key: 'invoices',
            name: 'Billing & Fee Records',
            domainNamespace: 'App\Domain\Invoices',
            description: 'Post-conversion billing, renewal fee invoices, and revenue ledger exports.',
            actions: [
                'invoices.view' => new ActionDefinition('invoices.view', 'invoices', 'View Invoices', 'Inspect system billing and receipt records', false, false),
                'invoices.generate' => new ActionDefinition('invoices.generate', 'invoices', 'Generate Invoice', 'Trigger ad-hoc invoice generation', false, true),
                'invoices.verify_payment' => new ActionDefinition('invoices.verify_payment', 'invoices', 'Verify Invoice Payment', 'Reconcile renewal fee payment', false, true),
                'invoices.export' => new ActionDefinition('invoices.export', 'invoices', 'Export Invoices', 'Export invoice lists and receipts to CSV/PDF', true, false),
            ],
            defaultRoute: 'crm.invoices',
        ));

        // 6. dashboard_reports
        $this->registerModule(new ModuleDefinition(
            key: 'dashboard_reports',
            name: 'Operational & Mission Analytics',
            domainNamespace: 'App\Domain\Reports',
            description: 'Workspace-scoped KPIs, pipeline analytics, and executive reporting.',
            actions: [
                'dashboard_reports.view_dashboard' => new ActionDefinition('dashboard_reports.view_dashboard', 'dashboard_reports', 'View Dashboard', 'Access operational KPI dashboard', false, false),
                'dashboard_reports.generate_reports' => new ActionDefinition('dashboard_reports.generate_reports', 'dashboard_reports', 'Generate Reports', 'Compile custom pipeline and demographic reports', true, false),
                'dashboard_reports.export_metrics' => new ActionDefinition('dashboard_reports.export_metrics', 'dashboard_reports', 'Export Metrics', 'Export operational summaries and charts', true, false),
            ],
            defaultRoute: 'crm.reports',
        ));

        // 7. platform_services
        $this->registerModule(new ModuleDefinition(
            key: 'platform_services',
            name: 'Platform Administration & Security',
            domainNamespace: 'App\Domain\Platform',
            description: 'User administration, settings, dynamic terms, role delegation, and audit logging.',
            actions: [
                'platform_services.manage_settings' => new ActionDefinition('platform_services.manage_settings', 'platform_services', 'Manage Settings', 'Configure workspace operational parameters', false, true),
                'platform_services.manage_users' => new ActionDefinition('platform_services.manage_users', 'platform_services', 'Manage Users', 'Provision staff accounts and manage credentials', false, true),
                'platform_services.delegate_roles' => new ActionDefinition('platform_services.delegate_roles', 'platform_services', 'Delegate Roles', 'Assign permitted operational roles to staff per DISC-04', false, true),
                'platform_services.view_audit_logs' => new ActionDefinition('platform_services.view_audit_logs', 'platform_services', 'View Audit Logs', 'Inspect append-only security logs per DEC-11', false, true),
                'platform_services.manage_backups' => new ActionDefinition('platform_services.manage_backups', 'platform_services', 'Manage Backups', 'Trigger backup snapshots and health checks', false, false),
            ],
            defaultRoute: 'crm.settings.index',
        ));

        // 8. migration
        $this->registerModule(new ModuleDefinition(
            key: 'migration',
            name: 'Legacy Zoho Ingestion & Data Reconciliation',
            domainNamespace: 'App\Domain\Migration',
            description: 'Legacy Zoho Forms and CRM ingestion, field transformation, and data reconciliation.',
            actions: [
                'migration.import_zoho' => new ActionDefinition('migration.import_zoho', 'migration', 'Import Zoho Data', 'Execute legacy Zoho data ingestion pipeline', false, true),
                'migration.export_zoho' => new ActionDefinition('migration.export_zoho', 'migration', 'Export Zoho Data', 'Export rollback datasets in Zoho format', false, true),
                'migration.reconcile_data' => new ActionDefinition('migration.reconcile_data', 'migration', 'Reconcile Data', 'Run automated reconciliation scripts', false, false),
            ],
            defaultRoute: 'crm.migration.reconciliation',
        ));
    }

    protected function registerModule(ModuleDefinition $module): void
    {
        $this->modules[$module->key] = $module;
        foreach ($module->actions as $token => $action) {
            $this->actions[$token] = $action;
        }
    }

    public function getModules(): array
    {
        return $this->modules;
    }

    public function getModule(string $moduleKey): ?ModuleDefinition
    {
        return $this->modules[$moduleKey] ?? null;
    }

    public function hasModule(string $moduleKey): bool
    {
        return isset($this->modules[$moduleKey]);
    }

    public function getDeclaredActions(): array
    {
        return $this->actions;
    }

    public function isActionRegistered(string $actionToken): bool
    {
        return isset($this->actions[$actionToken]);
    }

    public function isDelegable(string $actionToken): bool
    {
        return in_array($actionToken, self::DELEGABLE_ACTIONS, true);
    }

    public function isStrictlyNonDelegable(string $actionToken): bool
    {
        return in_array($actionToken, self::STRICTLY_NON_DELEGABLE, true);
    }

    /**
     * Set a workspace module enablement override for runtime/testing.
     */
    public function setWorkspaceModuleState(string $workspaceId, string $moduleKey, bool $enabled): void
    {
        $this->workspaceOverrides[$workspaceId][$moduleKey] = $enabled;
    }

    public function isModuleEnabledForWorkspace(string $moduleKey, ?string $workspaceId): bool
    {
        if (!$this->hasModule($moduleKey)) {
            return false;
        }

        // Global admin / Superadmin operations bypass workspace-level toggles
        if ($workspaceId === null) {
            return true;
        }

        if (isset($this->workspaceOverrides[$workspaceId][$moduleKey])) {
            return (bool) $this->workspaceOverrides[$workspaceId][$moduleKey];
        }

        // Workspace-specific config if defined
        $disabled = \Illuminate\Support\Facades\Config::get("tenancy.workspaces.{$workspaceId}.disabled_modules", []);
        if (is_array($disabled) && in_array($moduleKey, $disabled, true)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the canonical web path for a given module and workspace identifier.
     */
    public function getModulePath(string $moduleKey, string $workspaceId): string
    {
        return match ($moduleKey) {
            'dashboard_reports' => "/crm/{$workspaceId}/reports",
            'platform_services' => "/crm/{$workspaceId}/settings",
            'migration' => "/crm/{$workspaceId}/migration/reconciliation",
            default => "/crm/{$workspaceId}/{$moduleKey}",
        };
    }

    /**
     * Resolve the canonical route name for a given module.
     */
    public function getModuleRouteName(string $moduleKey): ?string
    {
        return $this->modules[$moduleKey]->defaultRoute ?? null;
    }
}
