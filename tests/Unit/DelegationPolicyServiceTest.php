<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\AccessControl\Exceptions\TenancyBoundaryException;
use App\Domain\AccessControl\Exceptions\UnauthorizedDelegationException;
use App\Domain\AccessControl\Services\DelegationPolicyService;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class DelegationPolicyServiceTest extends TestCase
{
    protected DelegationPolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DelegationPolicyService();
    }

    public function test_delegable_actions_allowlist_contains_expected_eight_actions(): void
    {
        $expected = [
            'forms.create',
            'forms.edit',
            'members.edit',
            'members.export_csv',
            'members.export_excel',
            'invoices.export',
            'dashboard_reports.generate_reports',
            'dashboard_reports.export_metrics',
        ];

        $this->assertCount(8, DelegationPolicyService::DELEGABLE_ACTIONS);
        $this->assertEquals($expected, DelegationPolicyService::DELEGABLE_ACTIONS);

        foreach ($expected as $action) {
            $this->assertTrue($this->service->isDelegable($action), "Action {$action} should be delegable");
            $this->assertFalse($this->service->isStrictlyNonDelegable($action), "Action {$action} should not be non-delegable");
        }
    }

    public function test_strictly_non_delegable_contains_eighteen_managerial_actions(): void
    {
        $expected = [
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

        $this->assertCount(18, DelegationPolicyService::STRICTLY_NON_DELEGABLE);

        foreach ($expected as $action) {
            $this->assertTrue($this->service->isStrictlyNonDelegable($action), "Action {$action} should be strictly non-delegable");
            $this->assertFalse($this->service->isDelegable($action), "Action {$action} should not be delegable");
        }
    }

    public function test_granting_delegable_action_within_same_workspace_succeeds(): void
    {
        $manager = new User();
        $manager->id = 1;
        $manager->workspace_id = 'ws-emk-dhaka';

        $staff = new User();
        $staff->id = 2;
        $staff->workspace_id = 'ws-emk-dhaka';

        // Should not throw any exception
        $this->service->assertCanDelegate($manager, 'forms.create', $staff);
        $this->service->assertCanDelegate($manager, 'members.export_csv', $staff);
        $this->service->assertCanDelegate($manager, 'dashboard_reports.generate_reports', $staff);

        $this->assertTrue(true);
    }

    public function test_attempting_to_delegate_across_workspaces_throws_tenancy_boundary_exception(): void
    {
        $manager = new User();
        $manager->id = 1;
        $manager->workspace_id = 'ws-emk-dhaka';

        $staff = new User();
        $staff->id = 2;
        $staff->workspace_id = 'ws-corner-sylhet';

        $this->expectException(TenancyBoundaryException::class);
        $this->expectExceptionMessage('Cannot delegate permissions across workspace boundaries.');

        $this->service->assertCanDelegate($manager, 'forms.create', $staff);
    }

    public function test_attempting_to_delegate_when_manager_has_null_workspace_throws_tenancy_boundary_exception(): void
    {
        $manager = new User();
        $manager->id = 1;
        $manager->workspace_id = null;

        $staff = new User();
        $staff->id = 2;
        $staff->workspace_id = 'ws-emk-dhaka';

        $this->expectException(TenancyBoundaryException::class);
        $this->expectExceptionMessage('Cannot delegate permissions across workspace boundaries.');

        $this->service->assertCanDelegate($manager, 'forms.create', $staff);
    }

    public function test_attempting_to_delegate_strictly_non_delegable_actions_throws_unauthorized_delegation_exception(): void
    {
        $manager = new User();
        $manager->id = 1;
        $manager->workspace_id = 'ws-emk-dhaka';

        $staff = new User();
        $staff->id = 2;
        $staff->workspace_id = 'ws-emk-dhaka';

        foreach (DelegationPolicyService::STRICTLY_NON_DELEGABLE as $action) {
            try {
                $this->service->assertCanDelegate($manager, $action, $staff);
                $this->fail("Expected UnauthorizedDelegationException was not thrown for non-delegable action: {$action}");
            } catch (UnauthorizedDelegationException $e) {
                $this->assertStringContainsString("Action {$action} is non-delegable to staff.", $e->getMessage());
            }
        }
    }

    public function test_attempting_to_delegate_unknown_action_throws_unauthorized_delegation_exception(): void
    {
        $manager = new User();
        $manager->id = 1;
        $manager->workspace_id = 'ws-emk-dhaka';

        $staff = new User();
        $staff->id = 2;
        $staff->workspace_id = 'ws-emk-dhaka';

        $this->expectException(UnauthorizedDelegationException::class);
        $this->expectExceptionMessage('Action unknown.arbitrary_action is non-delegable to staff.');

        $this->service->assertCanDelegate($manager, 'unknown.arbitrary_action', $staff);
    }
}
