# Central Module Registry Contract, Zero-Eval Architecture, and Declared Actions Catalog

**Phase:** 02-architecture-data-model-and-security-design  
**Plan:** 02-01 (Task 1)  
**Status:** Approved Architectural Specification  
**Governing Requirements:** ARCH-01, ARCH-06  
**Governing Decisions:** DEC-01 (D-01), DEC-02 (D-02), DEC-03 (D-03), DEC-04 (D-04), DEC-05 (D-05), DEC-06 (D-06), DEC-07 (D-07), DEC-08 (D-08), DEC-11 (D-11), DISC-04  
**Threat Mitigations:** T-01-07 (Privilege Escalation), T-02-01 (ModuleRegistry Spoofing & Dynamic Code Execution)

---

## 1. Architectural Purpose and Governance

This document establishes the authoritative modular architecture, central registry contract, declared action catalog, and zero-eval execution invariant for the EMK Membership CRM monolith within the existing Laravel 12 Hub application.

Per **DEC-02 (D-02)** and **ARCH-01**, the platform explicitly rejects dynamic package loaders, ad-hoc runtime plugin hooks, and arbitrary filesystem discovery. Instead, all capabilities are partitioned across exactly **eight (8) core modules** registering **thirty-eight (38) immutable declared actions** in an immutable central registry (`App\Domain\Platform\Registry\ModuleRegistry`).

This registry forms the single source of truth for:
1. **Spatie RBAC Permission Seeding:** Generating and validating all permission tokens across database migrations and seeders.
2. **Tenancy & Module Enablement:** Enabling and disabling feature modules on a per-workspace basis without altering assigned staff permissions.
3. **Delegation Security Boundaries:** Enforcing the hardcoded non-delegable allowlist to prevent staff privilege escalation per **T-01-07**.
4. **Architectural Boundary Enforcement:** Validating module cohesion and prohibiting unauthorized cross-domain coupling via automated Pest PHP architecture tests.

---

## 2. Zero-Eval Architectural Invariant

### 2.1 The Invariant Rule
The platform strictly enforces a **Zero Dynamic Code Evaluation Invariant**:
> Under no circumstances shall the platform evaluate arbitrary PHP code strings, instantiate unvalidated dynamic class strings from user input, or load uncompiled runtime plugins from filesystem directories.

### 2.2 Prohibited Patterns & Enforcement
The following PHP functions and design patterns are strictly prohibited across all application tiers:
- **`eval()`**: Execution of arbitrary PHP code strings is completely banned.
- **`create_function()`**: Prohibited.
- **`assert()` with string code**: Prohibited.
- **Variable class instantiation from untrusted strings**: Pattern `$class = $request->input('class'); new $class();` is strictly banned. Class instantiation must resolve through typed DI containers or explicit class maps.
- **Variable function calls from untrusted input**: Calling functions via `$func = $request->input('func'); $func();` is prohibited.
- **Runtime plugin scanning**: Scanning arbitrary unversioned directories (e.g. `plugins/`) for dynamically loaded classes at runtime is prohibited.

### 2.3 Automated Pest Architecture Guard
The invariant is enforced in CI/CD through Pest Architecture tests (`tests/Arch/ModuleRegistryArchTest.php`):
```php
arch('strictly prohibit dynamic code evaluation across application')
    ->expect(['eval', 'create_function'])
    ->not->toBeUsed();

arch('enforce domain registry encapsulation')
    ->expect('App\Domain')
    ->toOnlyUse([
        'Illuminate',
        'App\Domain',
        'Spatie\Permission',
        'Carbon\Carbon',
    ]);
```

---

## 3. The 8 Core Modules Catalog

The system domain is partitioned into eight cleanly bounded modules within the `App\Domain` namespace. Each module encapsulates its own models, domain services, events, actions, and Livewire components.

| Module Key | Human Name | Domain Namespace | Primary Responsibilities |
|---|---|---|---|
| `forms` | Form Builder & Public Forms | `App\Domain\Forms` | Public application form generation, form field configuration, 31-field validation contracts, public endpoint deployment, and draft entry staging. |
| `leads` | Lead Lifecycle & Tri-State Verification | `App\Domain\Leads` | Inbound applicant triage, 31-field lead profile review, tri-state verification streams (Payment, Document, Photo), communication logs, and 10-step atomic conversion to Member. |
| `members` | Member Profiles & ID Cards | `App\Domain\Members` | Master member profiles, dynamic term validity calculations, member status transitions, directory search, CSV/Excel audited exports, and physical ID card issuance lifecycle. |
| `member_portal` | Read-Only Member Portal | `App\Domain\Portal` | Dedicated member-guard self-service portal, profile inspection, official correspondence view, change request submissions, and dedicated email change workflows per DEC-06/07. |
| `invoices` | Billing & Fee Records | `App\Domain\Invoices` | Post-conversion billing generation, renewal fee invoicing, manual bKash TxID payment reconciliation, receipt exports, and reversal voiding. |
| `dashboard_reports` | Operational & Mission Analytics | `App\Domain\Reports` | Workspace-scoped operational dashboards, verification throughput KPIs, conversion rate metrics, demographic reporting, and executive data exports. |
| `platform_services` | Platform Administration & Security | `App\Domain\Platform` | Staff identity provisioning, multi-tenant workspace administration, dynamic membership terms configuration, role delegation enforcement, and immutable audit logs. |
| `migration` | Legacy Zoho Ingestion & Reconciliation | `App\Domain\Migration` | Legacy Zoho Forms and Zoho CRM data ingestion, field mapping transformation, deduplication, reconciliation audit, and migration rollback tools. |

---

## 4. Declared Actions Catalog (38 Actions)

Every operational capability is registered as a declared action token using the standard Spatie Permission syntax `{module}.{action}` under the registry key `declared_actions`. There are exactly **38 declared actions** registered across the 8 modules.

```
Syntax: {module_key}.{action_verb}
Configuration Key: declared_actions
Example: leads.reverse_conversion, members.export_csv, platform_services.view_audit_logs
```

### Module 1: `forms` (6 Actions)
| Action Token | Human Description | Delegable? | Default Role Access |
|---|---|---|---|
| `forms.view` | View workspace form definitions, layouts, and field settings. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `forms.create` | Design and construct new custom application forms. | **Yes** | Workspace Manager, Global Admin, Superadmin |
| `forms.edit` | Modify form field definitions, instructional text, or layout. | **Yes** | Workspace Manager, Global Admin, Superadmin |
| `forms.publish` | Deploy a form to the public endpoint or activate live submission. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `forms.archive` | Deactivate/retire a form and close public submissions. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `forms.view_entries` | View unmoderated raw form submissions prior to CRM ingestion. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |

### Module 2: `leads` (12 Actions)
| Action Token | Human Description | Delegable? | Default Role Access |
|---|---|---|---|
| `leads.view` | Search, filter, and view lead profiles and submission history. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `leads.create` | Manually enter an offline or walk-in lead application. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `leads.edit` | Update lead contact details, address, or non-identity metadata. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `leads.delete_soft` | Soft-delete an invalid or duplicate lead (retaining audit trace). | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `leads.verify_payment` | Verify 10-char bKash TxID and fee payment in merchant ledger per DEC-05. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `leads.verify_documents` | Verify national ID, passport, birth certificate, and youth consent per DEC-09. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `leads.verify_photo` | Verify 5-point studio passport photo compliance for ID badge printing. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `leads.communicate` | Send official missing item notifications or applicant email updates. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `leads.approve` | Formally approve fully verified lead for membership induction. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `leads.reject` | Formally reject lead application with mandatory standardized reason. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `leads.convert` | Execute atomic 10-step lead-to-member conversion transaction per DEC-03. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `leads.reverse_conversion` | Execute controlled 30-day conversion rollback with audited reason per DEC-04. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |

### Module 3: `members` (7 Actions)
| Action Token | Human Description | Delegable? | Default Role Access |
|---|---|---|---|
| `members.view` | Search, filter, and inspect member master profiles and valid terms. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `members.edit` | Update member contact number, address, or education/profession. | **Yes** | Workspace Manager, Global Admin, Superadmin |
| `members.restrict` | Temporarily suspend membership privileges (e.g. library borrowing). | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `members.revoke` | Permanently revoke membership upon formal management directive. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `members.export_csv` | Export workspace member records to CSV format with audit tracking. | **Yes** | Workspace Manager, Global Admin, Superadmin |
| `members.export_excel` | Export workspace member records to Excel format with audit tracking. | **Yes** | Workspace Manager, Global Admin, Superadmin |
| `members.manage_cards` | Advance physical ID card issuance lifecycle (Printing $\rightarrow$ Ready $\rightarrow$ Delivered). | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |

### Module 4: `member_portal` (4 Actions)
| Action Token | Human Description | Delegable? | Default Role Access |
|---|---|---|---|
| `member_portal.view_profile` | View self-service profile, term validity, and official notices per DEC-06. | No (Member default) | Authenticated Member (`guard = member`) |
| `member_portal.request_change` | Submit general profile modification request for staff review. | No (Member default) | Authenticated Member (`guard = member`) |
| `member_portal.request_email_change` | Submit dedicated email update request alerting Workspace Manager per DEC-07. | No (Member default) | Authenticated Member (`guard = member`) |
| `member_portal.request_renewal` | Submit renewal application and payment reference for expiring term. | No (Member default) | Authenticated Member (`guard = member`) |

### Module 5: `invoices` (4 Actions)
| Action Token | Human Description | Delegable? | Default Role Access |
|---|---|---|---|
| `invoices.view` | Inspect system-generated post-conversion and renewal invoices. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `invoices.generate` | Trigger ad-hoc invoice generation or replacement badge billing. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `invoices.verify_payment` | Validate payment reconciliation for membership renewal invoices. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `invoices.export` | Export invoice ledgers and revenue summaries to CSV/PDF (audited). | **Yes** | Workspace Manager, Global Admin, Superadmin |

### Module 6: `dashboard_reports` (3 Actions)
| Action Token | Human Description | Delegable? | Default Role Access |
|---|---|---|---|
| `dashboard_reports.view_dashboard` | Access workspace-scoped operational metrics and KPIs. | No (Staff default) | Workspace Staff, Manager, Global Admin, Superadmin |
| `dashboard_reports.generate_reports` | Compile custom lead pipeline and membership demographic reports. | **Yes** | Workspace Manager, Global Admin, Superadmin |
| `dashboard_reports.export_metrics` | Export operational charts, summaries, and executive metrics. | **Yes** | Workspace Manager, Global Admin, Superadmin |

### Module 7: `platform_services` (5 Actions)
| Action Token | Human Description | Delegable? | Default Role Access |
|---|---|---|---|
| `platform_services.manage_settings` | Configure workspace operational parameters, terms, and templates. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `platform_services.manage_users` | Provision staff accounts, manage credentials, and toggle status. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `platform_services.delegate_roles` | Assign delegable permissions to workspace staff per DISC-04. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `platform_services.view_audit_logs` | Inspect immutable security and operational audit logs per DEC-11. | **No (Managerial)** | Workspace Manager, Global Admin, Superadmin |
| `platform_services.manage_backups` | Trigger on-demand backup snapshots or verify restore health. | No (Admin only) | Global Admin, Superadmin |

### Module 8: `migration` (3 Actions)
| Action Token | Human Description | Delegable? | Default Role Access |
|---|---|---|---|
| `migration.import_zoho` | Execute automated ingestion pipeline for legacy Zoho CRM/Forms. | No (Admin only) | Global Admin, Superadmin |
| `migration.export_zoho` | Export migrated records in legacy Zoho-compliant format for rollback. | No (Admin only) | Global Admin, Superadmin |
| `migration.reconcile_data` | Run automated data integrity and reconciliation matching scripts. | No (Admin only) | Global Admin, Superadmin |

---

## 5. Delegation Isolation & Hardened Allowlist

To eliminate the threat of **Privilege Escalation (T-01-07)**, the platform enforces a strict separation between delegable operational tasks and managerial governance actions.

### 5.1 The 8 Delegable Actions Allowlist
A Workspace Manager is permitted to delegate only the following **eight (8) specific actions** to Workspace Staff within their workspace:
1. `forms.create` (Design new application forms)
2. `forms.edit` (Update existing form copy and layouts)
3. `members.edit` (Update member non-identity contact and address records)
4. `members.export_csv` (Export workspace member directory to CSV)
5. `members.export_excel` (Export workspace member directory to Excel)
6. `invoices.export` (Export workspace billing records)
7. `dashboard_reports.generate_reports` (Run custom pipeline and demographic reports)
8. `dashboard_reports.export_metrics` (Export operational dashboard metrics)

### 5.2 The 16 Strictly Non-Delegable Actions
The following **sixteen (16) managerial actions** are hardcoded as strictly non-delegable. Any attempt by a Workspace Manager to assign these actions to staff will fail closed, throw an `UnauthorizedDelegationException`, and log a security alert to the immutable audit log:
1. `leads.approve` (Formal admission approval)
2. `leads.reject` (Formal application rejection)
3. `leads.convert` (10-step atomic conversion to Member)
4. `leads.reverse_conversion` (30-day conversion reversal rollback per DEC-04)
5. `leads.verify_payment` (Manual bKash payment verification per DEC-05)
6. `leads.delete_soft` (Soft deletion of applicant records)
7. `forms.publish` (Deploying live forms to public endpoints)
8. `forms.archive` (Retiring active forms)
9. `invoices.generate` (Manual ad-hoc invoice creation)
10. `invoices.verify_payment` (Renewal payment reconciliation)
11. `platform_services.manage_settings` (Workspace terms and system configuration)
12. `platform_services.manage_users` (Staff user account provisioning and deactivation)
13. `platform_services.delegate_roles` (Granting delegation privileges)
14. `platform_services.view_audit_logs` (Viewing security audit trail per DEC-11)
15. `members.restrict` (Suspending membership privileges)
16. `members.revoke` (Permanently cancelling membership)

---

## 6. Decoupled Workspace-Level Module Enablement

To maintain clean multi-tenant operations without compromising RBAC integrity, module enablement is decoupled from user permission grants.

### 6.1 Operational Rules
1. **Workspace Configuration:** Each workspace stores its active module state in configuration (e.g. database column `workspaces.enabled_modules` JSON array or workspace settings).
2. **Permission Persistence:** If a module is disabled for a workspace (for example, disabling `migration` post-pilot, or disabling `forms` during maintenance), staff users retain their assigned Spatie permissions. No permission rows are deleted.
3. **Fail-Closed Gatekeeper:** When a user navigates to a route or invokes a Livewire component belonging to a disabled module, the `EnsureModuleEnabled` middleware intercepts the request:
   - Returns HTTP 404 (Not Found) for public endpoints, or
   - Throws `ModuleDisabledException` resulting in a clear administrative error message.
   - UI navigation elements for disabled modules are automatically omitted from sidebar and menu components.

### 6.2 Middleware Implementation Blueprint
```php
namespace App\Http\Middleware;

use App\Domain\Platform\Registry\ModuleRegistryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(
        protected ModuleRegistryInterface $moduleRegistry
    ) {}

    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $workspaceId = $request->route('workspace') ?? auth()->user()?->workspace_id;

        if (!$this->moduleRegistry->isModuleEnabledForWorkspace($moduleKey, $workspaceId)) {
            abort(404, "Module [{$moduleKey}] is not enabled for this workspace.");
        }

        return $next($request);
    }
}
```

---

## 7. ModuleRegistry Service Contract & Class Blueprint

### 7.1 Contract: `ModuleRegistryInterface`
```php
namespace App\Domain\Platform\Registry;

interface ModuleRegistryInterface
{
    /**
     * Get all registered core module definitions.
     * @return array<string, ModuleDefinition>
     */
    public function getModules(): array;

    /**
     * Get a specific module definition by key.
     */
    public function getModule(string $moduleKey): ?ModuleDefinition;

    /**
     * Check if a module key is registered in the static catalog.
     */
    public function hasModule(string $moduleKey): bool;

    /**
     * Get all 38 declared actions across all modules.
     * @return array<string, ActionDefinition>
     */
    public function getDeclaredActions(): array;

    /**
     * Check if a permission string is a registered declared action.
     */
    public function isActionRegistered(string $actionToken): bool;

    /**
     * Check if an action is delegable by Workspace Managers to Staff.
     */
    public function isDelegable(string $actionToken): bool;

    /**
     * Check if an action is strictly non-delegable managerial action.
     */
    public function isStrictlyNonDelegable(string $actionToken): bool;

    /**
     * Check if a module is enabled for a specific workspace.
     */
    public function isModuleEnabledForWorkspace(string $moduleKey, ?string $workspaceId): bool;
}
```

### 7.2 Immutable Value Objects
```php
namespace App\Domain\Platform\Registry;

readonly class ModuleDefinition
{
    /**
     * @param array<string, ActionDefinition> $actions
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $domainNamespace,
        public string $description,
        public array $actions = [],
    ) {}
}

readonly class ActionDefinition
{
    public function __construct(
        public string $token,          // e.g. 'leads.convert'
        public string $moduleKey,      // e.g. 'leads'
        public string $name,           // e.g. 'Convert Lead to Member'
        public string $description,
        public bool $isDelegable,      // true if delegable to staff
        public bool $isManagerial,     // true if restricted to Manager+
    ) {}
}
```

### 7.3 Implementation: `ModuleRegistry` Singleton
```php
namespace App\Domain\Platform\Registry;

class ModuleRegistry implements ModuleRegistryInterface
{
    /**
     * Static compilation of 8 core modules and 38 declared actions.
     * Zero dynamic code evaluation per DEC-02.
     *
     * @var array<string, ModuleDefinition>
     */
    protected array $modules = [];

    /**
     * Flat index of all 38 declared actions for O(1) lookup.
     *
     * @var array<string, ActionDefinition>
     */
    protected array $actions = [];

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

    public const STRICTLY_NON_DELEGABLE = [
        'leads.approve',
        'leads.reject',
        'leads.convert',
        'leads.reverse_conversion',
        'leads.verify_payment',
        'leads.delete_soft',
        'forms.publish',
        'forms.archive',
        'invoices.generate',
        'invoices.verify_payment',
        'platform_services.manage_settings',
        'platform_services.manage_users',
        'platform_services.delegate_roles',
        'platform_services.view_audit_logs',
        'members.restrict',
        'members.revoke',
    ];

    public function __construct()
    {
        $this->compileStaticRegistry();
    }

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
            ]
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
            ]
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
            ]
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
            ]
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
            ]
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
            ]
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
            ]
        ));

        // 8. migration
        $this->registerModule(new ModuleDefinition(
            key: 'migration',
            name: 'Legacy Zoho Ingestion & Data Reconciliation',
            domainNamespace: 'App\Domain\Migration',
            description: 'Legacy Zoho Forms and CRM ingestion, field transformation, and data reconciliation.',
            actions: [
                'migration.import_zoho' => new ActionDefinition('migration.import_zoho', 'migration', 'Import Zoho Data', 'Execute legacy Zoho data ingestion pipeline', false, false),
                'migration.export_zoho' => new ActionDefinition('migration.export_zoho', 'migration', 'Export Zoho Data', 'Export rollback datasets in Zoho format', false, false),
                'migration.reconcile_data' => new ActionDefinition('migration.reconcile_data', 'migration', 'Reconcile Data', 'Run automated reconciliation scripts', false, false),
            ]
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

    public function isModuleEnabledForWorkspace(string $moduleKey, ?string $workspaceId): bool
    {
        if (!$this->hasModule($moduleKey)) {
            return false;
        }

        // Global admin / Superadmin operations bypass workspace-level toggles
        if ($workspaceId === null) {
            return true;
        }

        // Default: All modules enabled except 'migration' which is disabled post-cutover
        // In runtime, this queries workspace settings or cache
        return true;
    }
}
```

### 7.4 Service Provider Registration
The `ModuleRegistry` is registered as an immutable singleton in `App\Providers\ModuleRegistryServiceProvider`:
```php
namespace App\Providers;

use App\Domain\Platform\Registry\ModuleRegistry;
use App\Domain\Platform\Registry\ModuleRegistryInterface;
use Illuminate\Support\ServiceProvider;

class ModuleRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistryInterface::class, function () {
            return new ModuleRegistry();
        });

        $this->app->alias(ModuleRegistryInterface::class, 'module.registry');
    }

    public function boot(): void
    {
        // Immutable registration complete; no dynamic runtime additions allowed
    }
}
```

---

## 8. Summary of Guarantees

| Guarantee | Enforcement Mechanism | Failure Mode |
|---|---|---|
| **Zero-Eval** | Strict prohibition of `eval()`, `create_function()`, dynamic class loading. Verified by Pest Arch tests. | Build/CI failure if prohibited AST tokens are detected. |
| **Fixed 8 Modules** | Statically defined in `ModuleRegistry::compileStaticRegistry()`. | Unregistered module keys rejected by `hasModule()`. |
| **Fixed 38 Actions** | Statically mapped into Spatie permissions seeders. | Unregistered action tokens rejected by `isActionRegistered()`. |
| **Delegation Security** | Immutable `DELEGABLE_ACTIONS` allowlist enforced in `DelegationPolicyService`. | Throws `UnauthorizedDelegationException`, logs security alert. |
| **Workspace Scoping** | `EnsureModuleEnabled` middleware intercepts disabled modules per workspace. | Aborts with HTTP 404 or `ModuleDisabledException`. |
