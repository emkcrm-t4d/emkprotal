<?php

declare(strict_types=1);

use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Domain\Platform\Registry\ModuleRegistry;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Livewire\Migration\ReconciliationDashboard;

arch('phase 11 domains and components strictly forbid withoutGlobalScope per DEC-01')
    ->expect([
        'App\Domain\Migration',
        'App\Livewire\Migration',
    ])
    ->not->toUse('withoutGlobalScope')
    ->not->toUse('withoutGlobalScopes');

arch('phase 11 domains and components strictly forbid dynamic code evaluation per DEC-02')
    ->expect([
        'App\Domain\Migration',
        'App\Livewire\Migration',
    ])
    ->not->toUse(['eval', 'create_function']);

arch('phase 11 domain classes never use global route helper per architecture discipline')
    ->expect('App\Domain\Migration')
    ->not->toUse('route');

arch('phase 11 domains strictly declare strict types')
    ->expect('App\Domain\Migration')
    ->toUseStrictTypes();

arch('phase 11 migration models implement TenantEntityInterface and use BelongsToTenant')
    ->expect([
        ZohoImportBatch::class,
        ZohoStagingRecord::class,
    ])
    ->toImplement(TenantEntityInterface::class)
    ->toUse(BelongsToTenant::class);

test('phase 11 livewire components implement workspace hydration guards per DEC-01', function () {
    $componentClass = ReconciliationDashboard::class;
    $traits = class_uses_recursive($componentClass);
    expect($traits)->toHaveKey(ValidatesWorkspaceOnHydration::class);
});

test('reconciliation dashboard component authorizes migration reconcile_data permission', function () {
    $ref = new ReflectionClass(ReconciliationDashboard::class);
    expect($ref->hasMethod('authorizeAccess'))->toBeTrue();

    $content = file_get_contents($ref->getFileName());
    expect($content)->toContain("can('migration.reconcile_data')");
});

test('module registry declares and locks phase 11 migration actions per T-01-07 and T-11-13', function () {
    $registry = app(ModuleRegistry::class);

    expect($registry->hasModule('migration'))->toBeTrue();
    expect($registry->getModuleRouteName('migration'))->toBe('crm.migration.reconciliation');
    expect($registry->getModulePath('migration', 'ws-emk-dhaka'))->toBe('/crm/ws-emk-dhaka/migration/reconciliation');

    expect($registry->isActionRegistered('migration.import_zoho'))->toBeTrue();
    expect($registry->isActionRegistered('migration.export_zoho'))->toBeTrue();
    expect($registry->isActionRegistered('migration.reconcile_data'))->toBeTrue();

    // Managerial non-delegable actions per T-01-07 and T-11-13
    expect(ModuleRegistry::STRICTLY_NON_DELEGABLE)->toContain('migration.import_zoho');
    expect(ModuleRegistry::STRICTLY_NON_DELEGABLE)->toContain('migration.export_zoho');

    expect($registry->isStrictlyNonDelegable('migration.import_zoho'))->toBeTrue();
    expect($registry->isDelegable('migration.import_zoho'))->toBeFalse();

    expect($registry->isStrictlyNonDelegable('migration.export_zoho'))->toBeTrue();
    expect($registry->isDelegable('migration.export_zoho'))->toBeFalse();

    // Reconcile data action is operational but not delegable to staff
    expect($registry->isDelegable('migration.reconcile_data'))->toBeFalse();
});

test('phase 11 domain classes strictly avoid global route helper in source code', function () {
    $dir = realpath(__DIR__ . '/../../app/Domain/Migration');
    expect($dir)->not->toBeFalse();

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            // Assert that global route( helper is never called
            expect(preg_match('/(?<!->|::|\bfunction\s)\broute\s*\(/i', $content))
                ->toBe(0, "File [{$file->getPathname()}] uses global route() helper instead of URL::route().");
        }
    }
});
