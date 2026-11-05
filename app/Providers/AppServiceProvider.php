<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Domain\Payments\Services\PaymentManager::class, function ($app) {
            return new \App\Domain\Payments\Services\PaymentManager($app);
        });

        $this->app->bind(\App\Domain\Payments\Contracts\PaymentAdapterInterface::class, function ($app) {
            return $app->make(\App\Domain\Payments\Services\PaymentManager::class)->driver();
        });

        $this->app->bind(
            \App\Domain\Mail\Contracts\ImapClientInterface::class,
            \App\Domain\Mail\Services\ImapMailboxAdapter::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }
            if (method_exists($user, 'isGlobalAdmin') && $user->isGlobalAdmin()) {
                return true;
            }
            return null;
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Domain\Migration\Commands\ZohoImportCommand::class,
                \App\Domain\Migration\Commands\ZohoImportAttachmentsCommand::class,
                \App\Domain\Migration\Commands\ZohoReconcileCommand::class,
            ]);
        }
    }
}
