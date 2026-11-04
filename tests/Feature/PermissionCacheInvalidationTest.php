<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\AccessControl\Listeners\InvalidatePermissionCacheListener;
use App\Domain\AccessControl\Services\DelegationPolicyService;
use App\Domain\AccessControl\Services\StaffManagementService;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staff;
    protected StaffManagementService $staffService;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed space & workspace
        DB::table('spaces')->insert([
            'id' => 1,
            'code' => 'EMK-DHK',
            'name' => 'EMK Center Dhaka',
            'category' => 'Center',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspaces')->insert([
            'id' => 'ws-emk-dhaka',
            'space_id' => 1,
            'name' => 'EMK Center Dhaka',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Seed roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        // 3. Create users
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'cache.manager.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Delegated Staff',
            'email' => 'cache.staff.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        $this->staffService = app(StaffManagementService::class);
    }

    public function test_cache_contains_permission_grants_after_loading_permissions(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $this->assertFalse(Cache::has('spatie.permission.cache'));

        // Loading permissions populates the cache
        $registrar->getPermissions();

        $this->assertTrue(Cache::has('spatie.permission.cache'));
    }

    public function test_revoking_permission_via_staff_management_service_evicts_cache_and_updates_authorization_immediately(): void
    {
        // 1. Delegate permission forms.create to staff
        $this->staffService->delegatePermission($this->manager, $this->staff, 'forms.create');

        // Refresh model relations to ensure clean initial state
        $this->staff->unsetRelation('permissions');
        $this->staff->unsetRelation('roles');

        // Warm up cache
        app(PermissionRegistrar::class)->getPermissions();
        $this->assertTrue(Cache::has('spatie.permission.cache'));
        $this->assertTrue($this->staff->can('forms.create'));

        // 2. Revoke permission
        $this->staffService->revokeDelegatedPermission($this->manager, $this->staff, 'forms.create');

        // Cache must be evicted immediately
        $this->assertFalse(
            Cache::has('spatie.permission.cache'),
            'Cache key spatie.permission.cache must be evicted immediately upon permission revocation.'
        );

        // 3. Subsequent authorization check must return false immediately without manual intervention
        $this->staff->unsetRelation('permissions');
        $this->assertFalse(
            $this->staff->can('forms.create'),
            'User authorization check must immediately evaluate to false after revocation.'
        );
    }

    public function test_delegating_permission_via_staff_management_service_immediately_authorizes_subsequent_check(): void
    {
        $this->staff->unsetRelation('permissions');
        $this->assertFalse($this->staff->can('forms.create'));

        // Delegate permission
        $this->staffService->delegatePermission($this->manager, $this->staff, 'forms.create');

        // Subsequent check must return true immediately
        $this->staff->unsetRelation('permissions');
        $this->assertTrue(
            $this->staff->can('forms.create'),
            'User authorization check must immediately evaluate to true after delegation.'
        );
    }

    public function test_listener_evicts_cache_and_logs_debug_message(): void
    {
        Log::shouldReceive('debug')
            ->with('Spatie permission cache invalidated.')
            ->atLeast()
            ->once();

        // Warm cache
        app(PermissionRegistrar::class)->getPermissions();
        $this->assertTrue(Cache::has('spatie.permission.cache'));

        $listener = app(InvalidatePermissionCacheListener::class);
        $listener->invalidate();

        $this->assertFalse(Cache::has('spatie.permission.cache'));
    }

    public function test_spatie_native_events_trigger_cache_invalidation(): void
    {
        $permission = Permission::findByName('forms.create', 'web');

        // Warm up cache
        app(PermissionRegistrar::class)->getPermissions();
        $this->assertTrue(Cache::has('spatie.permission.cache'));

        // Fire Spatie permission attached event
        event(new PermissionAttached($this->staff, $permission));

        $this->assertFalse(
            Cache::has('spatie.permission.cache'),
            'PermissionAttached event must trigger InvalidatePermissionCacheListener to evict cache.'
        );

        // Warm up cache again
        app(PermissionRegistrar::class)->getPermissions();
        $this->assertTrue(Cache::has('spatie.permission.cache'));

        // Fire Spatie permission detached event
        event(new PermissionDetached($this->staff, $permission));

        $this->assertFalse(
            Cache::has('spatie.permission.cache'),
            'PermissionDetached event must trigger InvalidatePermissionCacheListener to evict cache.'
        );
    }
}
