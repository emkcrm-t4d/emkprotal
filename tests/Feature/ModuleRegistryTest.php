<?php

declare(strict_types=1);

use App\Domain\Platform\Registry\ActionDefinition;
use App\Domain\Platform\Registry\ModuleDefinition;
use App\Domain\Platform\Registry\ModuleRegistry;
use App\Domain\Platform\Registry\ModuleRegistryInterface;
use App\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->registry = app(ModuleRegistryInterface::class);
});

test('module registry returns exactly 8 core modules', function () {
    $modules = $this->registry->getModules();

    expect($modules)->toBeArray()
        ->and(count($modules))->toBe(8);

    $expectedModules = [
        'forms',
        'leads',
        'members',
        'member_portal',
        'invoices',
        'dashboard_reports',
        'platform_services',
        'migration',
    ];

    foreach ($expectedModules as $key) {
        expect($this->registry->hasModule($key))->toBeTrue()
            ->and($this->registry->getModule($key))->toBeInstanceOf(ModuleDefinition::class)
            ->and($this->registry->getModule($key)->key)->toBe($key);
    }
});

test('module registry returns declared actions catalog', function () {
    $actions = $this->registry->getDeclaredActions();

    expect($actions)->toBeArray()
        ->and(count($actions))->toBe(44);

    foreach ($actions as $token => $action) {
        expect($action)->toBeInstanceOf(ActionDefinition::class)
            ->and($action->token)->toBe($token)
            ->and($this->registry->hasModule($action->moduleKey))->toBeTrue();
    }
});

test('isActionRegistered returns true for valid action tokens and false for unknown tokens', function () {
    expect($this->registry->isActionRegistered('leads.convert'))->toBeTrue()
        ->and($this->registry->isActionRegistered('leads.reverse_conversion'))->toBeTrue()
        ->and($this->registry->isActionRegistered('forms.publish'))->toBeTrue()
        ->and($this->registry->isActionRegistered('members.export_csv'))->toBeTrue()
        ->and($this->registry->isActionRegistered('invoices.export'))->toBeTrue()
        ->and($this->registry->isActionRegistered('platform_services.view_audit_logs'))->toBeTrue()
        ->and($this->registry->isActionRegistered('migration.import_zoho'))->toBeTrue()
        ->and($this->registry->isActionRegistered('leads.hack'))->toBeFalse()
        ->and($this->registry->isActionRegistered('invalid.token'))->toBeFalse()
        ->and($this->registry->isActionRegistered(''))->toBeFalse();
});

test('isStrictlyNonDelegable correctly identifies 18 managerial actions per T-01-07', function () {
    $nonDelegable = ModuleRegistry::STRICTLY_NON_DELEGABLE;

    expect(count($nonDelegable))->toBe(18);

    foreach ($nonDelegable as $token) {
        expect($this->registry->isStrictlyNonDelegable($token))->toBeTrue()
            ->and($this->registry->isDelegable($token))->toBeFalse();
    }

    // Explicit check for key managerial security actions
    expect($this->registry->isStrictlyNonDelegable('leads.reverse_conversion'))->toBeTrue()
        ->and($this->registry->isStrictlyNonDelegable('leads.convert'))->toBeTrue()
        ->and($this->registry->isStrictlyNonDelegable('leads.verify_payment'))->toBeTrue()
        ->and($this->registry->isStrictlyNonDelegable('platform_services.delegate_roles'))->toBeTrue()
        ->and($this->registry->isStrictlyNonDelegable('platform_services.manage_users'))->toBeTrue()
        ->and($this->registry->isStrictlyNonDelegable('members.revoke'))->toBeTrue();

    // Delegable actions must not be marked strictly non-delegable
    expect($this->registry->isStrictlyNonDelegable('members.edit'))->toBeFalse()
        ->and($this->registry->isStrictlyNonDelegable('forms.create'))->toBeFalse()
        ->and($this->registry->isStrictlyNonDelegable('invoices.export'))->toBeFalse();
});

test('isDelegable correctly identifies the 8 delegable actions allowlist', function () {
    $delegable = ModuleRegistry::DELEGABLE_ACTIONS;

    expect(count($delegable))->toBe(8);

    foreach ($delegable as $token) {
        expect($this->registry->isDelegable($token))->toBeTrue()
            ->and($this->registry->isStrictlyNonDelegable($token))->toBeFalse();
    }
});

test('EnsureModuleEnabled middleware allows enabled modules for workspace', function () {
    $middleware = new EnsureModuleEnabled($this->registry);

    $request = Request::create('/crm/ws-emk-dhaka/leads', 'GET');
    $request->attributes->set('workspace', 'ws-emk-dhaka');

    $response = $middleware->handle($request, function ($req) {
        return new Response('Allowed', 200);
    }, 'leads');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('Allowed');
});

test('EnsureModuleEnabled middleware aborts with 404 for disabled module', function () {
    $middleware = new EnsureModuleEnabled($this->registry);

    // Disable migration module for ws-emk-dhaka
    $this->registry->setWorkspaceModuleState('ws-emk-dhaka', 'migration', false);

    $request = Request::create('/crm/ws-emk-dhaka/migration', 'GET');
    $request->attributes->set('workspace', 'ws-emk-dhaka');

    try {
        $middleware->handle($request, function ($req) {
            return new Response('Should not reach here', 200);
        }, 'migration');

        $this->fail('Expected 404 HttpException was not thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404)
            ->and($e->getMessage())->toContain('Module [migration] is not enabled for this workspace.');
    }
});

test('EnsureModuleEnabled middleware aborts with 404 for unregistered module', function () {
    $middleware = new EnsureModuleEnabled($this->registry);

    $request = Request::create('/crm/ws-emk-dhaka/nonexistent', 'GET');

    try {
        $middleware->handle($request, function ($req) {
            return new Response('Should not reach here', 200);
        }, 'nonexistent');

        $this->fail('Expected 404 HttpException was not thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404)
            ->and($e->getMessage())->toContain('Module [nonexistent] is not enabled for this workspace.');
    }
});
