<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Mail\Models\WorkflowRule;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Platform\Registry\ModuleRegistry;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Livewire\Component;

// 1. System-wide Tenancy Isolation (DEC-01)
arch('system-wide tenancy isolation strictly forbids withoutGlobalScope per DEC-01')
    ->expect([
        'App\Domain',
        'App\Livewire',
    ])
    ->not->toUse('withoutGlobalScope')
    ->not->toUse('withoutGlobalScopes');

// 2. Zero Dynamic Code Execution (DEC-02)
arch('application code strictly forbids dynamic evaluation per DEC-02')
    ->expect('App')
    ->not->toUse(['eval', 'create_function']);

// 3. Route Helper Discipline
arch('domain classes do not invoke global route helper per architecture discipline')
    ->expect('App\Domain')
    ->not->toUse('route');

// 4. Strict Types Enforcement
arch('domain classes strictly declare strict types')
    ->expect('App\Domain')
    ->toUseStrictTypes();

// 5. Tenant Model Implementation
arch('all workspace domain models implement TenantEntityInterface and use BelongsToTenant')
    ->expect([
        Lead::class,
        LeadAttachment::class,
        Member::class,
        MemberChangeRequest::class,
        Form::class,
        FormSubmission::class,
        MembershipTerm::class,
        Invoice::class,
        IdCard::class,
        WorkflowRule::class,
        EmailThread::class,
        EmailTemplate::class,
        EmailMessage::class,
        PlatformSetting::class,
        ZohoImportBatch::class,
        ZohoStagingRecord::class,
        AuditLog::class,
    ])
    ->toImplement(TenantEntityInterface::class)
    ->toUse(BelongsToTenant::class);

// 6. Livewire Hydration Protection (DEC-01)
test('all workspace livewire components implement ValidatesWorkspaceOnHydration per DEC-01', function () {
    $dir = realpath(__DIR__ . '/../../app/Livewire');
    expect($dir)->not->toBeFalse();

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $checkedCount = 0;

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = str_replace([$dir . DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
            $className = 'App\\Livewire\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

            // Exclude public forms, portal member components, and navigation shell
            if (
                str_starts_with($className, 'App\\Livewire\\Portal\\') ||
                $className === 'App\\Livewire\\Forms\\PublicMembershipForm' ||
                $className === 'App\\Livewire\\Admin\\AdminShell'
            ) {
                continue;
            }

            if (class_exists($className) && is_subclass_of($className, Component::class)) {
                $traits = class_uses_recursive($className);
                expect(isset($traits[ValidatesWorkspaceOnHydration::class]))
                    ->toBeTrue("Component [{$className}] does not use ValidatesWorkspaceOnHydration trait.");
                $checkedCount++;
            }
        }
    }

    expect($checkedCount)->toBeGreaterThanOrEqual(30);
});

// Static source inspection for zero dynamic evaluation
test('application codebase strictly avoids eval and create_function in source code per DEC-02', function () {
    $dir = realpath(__DIR__ . '/../../app');
    expect($dir)->not->toBeFalse();

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $tokens = token_get_all(file_get_contents($file->getPathname()));

            foreach ($tokens as $token) {
                if (is_array($token)) {
                    $tokenType = $token[0];
                    $tokenText = strtolower($token[1]);

                    expect($tokenType)->not->toBe(T_EVAL, "File [{$file->getPathname()}] contains eval() statement.");

                    if ($tokenType === T_STRING && $tokenText === 'create_function') {
                        $this->fail("File [{$file->getPathname()}] contains create_function() call.");
                    }
                }
            }
        }
    }
});

// Static source inspection for route helper discipline
test('domain classes strictly avoid global route helper in source code per architecture discipline', function () {
    $dir = realpath(__DIR__ . '/../../app/Domain');
    expect($dir)->not->toBeFalse();

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());

            $matches = preg_match('/(?<!->|::|\bfunction\s)\broute\s*\(/i', $content);
            expect($matches)->toBe(0, "File [{$file->getPathname()}] uses global route() helper instead of URL::route().");
        }
    }
});

// 7. Module Registry Conformance
test('module registry conformance locks all 18 non-delegable and 8 delegable actions', function () {
    $registry = app(ModuleRegistry::class);

    expect(ModuleRegistry::STRICTLY_NON_DELEGABLE)->toHaveCount(18);
    expect(ModuleRegistry::DELEGABLE_ACTIONS)->toHaveCount(8);

    $expectedNonDelegable = [
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

    foreach ($expectedNonDelegable as $action) {
        expect($registry->isActionRegistered($action))->toBeTrue("Action [{$action}] is not registered in ModuleRegistry.");
        expect($registry->isStrictlyNonDelegable($action))->toBeTrue("Action [{$action}] is not marked strictly non-delegable.");
        expect($registry->isDelegable($action))->toBeFalse("Action [{$action}] should not be delegable.");
        expect(ModuleRegistry::STRICTLY_NON_DELEGABLE)->toContain($action);
    }

    $expectedDelegable = [
        'forms.create',
        'forms.edit',
        'members.edit',
        'members.export_csv',
        'members.export_excel',
        'invoices.export',
        'dashboard_reports.generate_reports',
        'dashboard_reports.export_metrics',
    ];

    foreach ($expectedDelegable as $action) {
        expect($registry->isActionRegistered($action))->toBeTrue("Action [{$action}] is not registered in ModuleRegistry.");
        expect($registry->isDelegable($action))->toBeTrue("Action [{$action}] is not marked delegable.");
        expect($registry->isStrictlyNonDelegable($action))->toBeFalse("Action [{$action}] should not be non-delegable.");
        expect(ModuleRegistry::DELEGABLE_ACTIONS)->toContain($action);
    }
});
