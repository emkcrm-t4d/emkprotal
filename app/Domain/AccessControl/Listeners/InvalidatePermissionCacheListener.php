<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Listeners;

use App\Domain\AccessControl\Events\StaffPermissionDelegated;
use App\Domain\AccessControl\Events\StaffPermissionRevoked;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class InvalidatePermissionCacheListener
{
    public function __construct(
        protected ?PermissionRegistrar $permissionRegistrar = null
    ) {
        $this->permissionRegistrar ??= app(PermissionRegistrar::class);
    }

    /**
     * Handle the cache invalidation trigger.
     */
    public function handle(mixed $event = null): void
    {
        $this->invalidate();
    }

    /**
     * Invalidate Spatie permission cache and evict keys across cache drivers.
     */
    public function invalidate(): void
    {
        // 1. Flush registrar internal state and default cache key
        $this->permissionRegistrar->forgetCachedPermissions();

        // 2. Explicitly evict cache key from active and redis stores if present
        $cacheKey = (string) config('permission.cache.key', 'spatie.permission.cache');

        try {
            Cache::forget($cacheKey);
        } catch (\Throwable) {
            // Non-blocking if cache store is unreachable
        }

        try {
            if (config('cache.stores.redis') !== null) {
                Cache::store('redis')->forget($cacheKey);
            }
        } catch (\Throwable) {
            // Non-blocking if redis store is not booted
        }

        // 3. Log debug record per AUTH-05 specification
        Log::debug('Spatie permission cache invalidated.');
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param Dispatcher $events
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        $subscribedEvents = [
            StaffPermissionDelegated::class => 'handle',
            StaffPermissionRevoked::class => 'handle',
        ];

        // Spatie events
        $spatieEvents = [
            'Spatie\Permission\Events\RoleAttached',
            'Spatie\Permission\Events\RoleDetached',
            'Spatie\Permission\Events\PermissionAttached',
            'Spatie\Permission\Events\PermissionDetached',
            'Spatie\Permission\Events\RoleAssigned',
            'Spatie\Permission\Events\RoleRevoked',
            'Spatie\Permission\Events\PermissionRevoked',
        ];

        foreach ($spatieEvents as $eventClass) {
            if (class_exists($eventClass)) {
                $subscribedEvents[$eventClass] = 'handle';
            }
        }

        return $subscribedEvents;
    }
}
