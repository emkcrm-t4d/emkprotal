<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Platform\Registry\ModuleRegistryInterface;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Clear Spatie permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 2. Resolve ModuleRegistryInterface from app container
        /** @var ModuleRegistryInterface $moduleRegistry */
        $moduleRegistry = app(ModuleRegistryInterface::class);
        $declaredActions = $moduleRegistry->getDeclaredActions();

        // 3. Seed all declared actions
        foreach ($declaredActions as $token => $action) {
            // Web guard permissions
            Permission::findOrCreate($token, 'web');

            // Member portal permissions for member guard
            if (str_starts_with($token, 'member_portal.')) {
                Permission::findOrCreate($token, 'member');
            }
        }

        // 4. Create the 5 Canonical Roles
        // Role 1: superadmin (guard web)
        $superadmin = Role::findOrCreate('superadmin', 'web');
        $superadmin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Role 2: global_admin (guard web)
        $globalAdmin = Role::findOrCreate('global_admin', 'web');
        $globalAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Role 3: workspace_manager (guard web)
        $workspaceManager = Role::findOrCreate('workspace_manager', 'web');
        $managerPermissions = Permission::where('guard_name', 'web')
            ->where(function ($query) {
                $query->where('name', 'like', 'forms.%')
                    ->orWhere('name', 'like', 'leads.%')
                    ->orWhere('name', 'like', 'members.%')
                    ->orWhere('name', 'like', 'invoices.%')
                    ->orWhere('name', 'like', 'dashboard_reports.%')
                    ->orWhereIn('name', [
                        'platform_services.manage_settings',
                        'platform_services.manage_users',
                        'platform_services.delegate_roles',
                        'platform_services.view_audit_logs',
                    ]);
            })->get();
        $workspaceManager->syncPermissions($managerPermissions);

        // Role 4: delegated_staff (guard web)
        $delegatedStaff = Role::findOrCreate('delegated_staff', 'web');
        $staffTokens = [
            'forms.view',
            'forms.view_entries',
            'leads.view',
            'leads.create',
            'leads.edit',
            'leads.verify_documents',
            'leads.verify_photo',
            'leads.communicate',
            'members.view',
            'members.manage_cards',
            'invoices.view',
            'dashboard_reports.view_dashboard',
        ];
        $delegatedStaff->syncPermissions(
            Permission::where('guard_name', 'web')->whereIn('name', $staffTokens)->get()
        );

        // Role 5: member (guard member)
        $memberRole = Role::findOrCreate('member', 'member');
        $memberRole->syncPermissions(Permission::where('guard_name', 'member')->get());

        // 5. Seed default administrative users
        $superadminUser = User::updateOrCreate(
            ['email' => 'admin@emk.test'],
            [
                'name' => 'Super Administrator',
                'workspace_id' => 'ws-emk-dhaka',
                'password' => Hash::make('secret123'),
                'guard' => 'web',
                'is_active' => true,
                'must_reset_password' => false,
            ]
        );
        $superadminUser->assignRole('superadmin');

        $managerUser = User::updateOrCreate(
            ['email' => 'manager.dhaka@emk.test'],
            [
                'name' => 'EMK Dhaka Workspace Manager',
                'workspace_id' => 'ws-emk-dhaka',
                'password' => Hash::make('secret123'),
                'guard' => 'web',
                'is_active' => true,
                'must_reset_password' => false,
            ]
        );
        $managerUser->assignRole('workspace_manager');
    }
}
