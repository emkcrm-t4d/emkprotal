<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Listeners\AuditAuthenticationEvents;
use App\Domain\Audit\Observers\AuditableObserver;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Subscribe authentication audit event listener
        Event::subscribe(AuditAuthenticationEvents::class);

        // 2. Attach AuditableObserver to primary auditable models
        User::observe(AuditableObserver::class);
        Lead::observe(AuditableObserver::class);
    }
}
