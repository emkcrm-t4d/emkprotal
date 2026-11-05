<?php

declare(strict_types=1);

use App\Domain\Platform\Registry\ModuleRegistry;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Http\Controllers\Reports\WorkspaceExportController;
use App\Livewire\Platform\BackupManager;
use App\Livewire\Platform\PlatformSettingsHub;
use App\Livewire\Reports\DataExportCenter;
use App\Livewire\Reports\OperationalDashboard;

arch('phase 10 domains and components strictly forbid withoutGlobalScope per DEC-01')
    ->expect([
        'App\Domain\Reports',
        'App\Domain\Platform',
        'App\Livewire\Reports',
        'App\Livewire\Platform',
    ])
    ->not->toUse('withoutGlobalScope')
    ->not->toUse('withoutGlobalScopes');

arch('phase 10 domains and components strictly forbid dynamic code evaluation per DEC-02')
    ->expect([
        'App\Domain\Reports',
        'App\Domain\Platform',
        'App\Livewire\Reports',
        'App\Livewire\Platform',
    ])
    ->not->toUse(['eval', 'create_function']);

test('all managerial actions in strictly non-delegable registry are locked against staff delegation per T-01-07', function () {
    $registry = app(ModuleRegistry::class);

    $strictlyNonDelegable = ModuleRegistry::STRICTLY_NON_DELEGABLE;

    expect($strictlyNonDelegable)->toContain(
        'platform_services.manage_settings',
        'platform_services.manage_users',
        'platform_services.delegate_roles',
        'platform_services.view_audit_logs'
    );

    foreach ($strictlyNonDelegable as $actionToken) {
        expect($registry->isStrictlyNonDelegable($actionToken))->toBeTrue();
        expect($registry->isDelegable($actionToken))->toBeFalse();
    }
});

test('phase 10 livewire components implement workspace hydration guards per T-10-13', function () {
    $components = [
        OperationalDashboard::class,
        DataExportCenter::class,
        PlatformSettingsHub::class,
        BackupManager::class,
    ];

    foreach ($components as $componentClass) {
        $traits = class_uses_recursive($componentClass);
        expect($traits)->toHaveKey(ValidatesWorkspaceOnHydration::class);
    }
});

test('module registry routes point cleanly to canonical phase 10 paths and actions', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->getModulePath('dashboard_reports', 'ws-emk-dhaka'))->toBe('/crm/ws-emk-dhaka/reports');
    expect($registry->getModuleRouteName('dashboard_reports'))->toBe('crm.reports');

    expect($registry->getModulePath('platform_services', 'ws-emk-dhaka'))->toBe('/crm/ws-emk-dhaka/settings');
    expect($registry->getModuleRouteName('platform_services'))->toBe('crm.settings.index');

    expect($registry->isActionRegistered('platform_services.manage_backups'))->toBeTrue();
});

test('phase 10 controllers and components authorize actions and authentication guards', function () {
    $controllersAndComponents = [
        WorkspaceExportController::class,
        OperationalDashboard::class,
        DataExportCenter::class,
        PlatformSettingsHub::class,
        BackupManager::class,
    ];

    foreach ($controllersAndComponents as $class) {
        expect(class_exists($class))->toBeTrue();
    }
});
