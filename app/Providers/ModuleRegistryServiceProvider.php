<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Platform\Registry\ModuleRegistry;
use App\Domain\Platform\Registry\ModuleRegistryInterface;
use Illuminate\Support\ServiceProvider;

class ModuleRegistryServiceProvider extends ServiceProvider
{
    /**
     * Register the immutable ModuleRegistry singleton.
     */
    public function register(): void
    {
        $this->app->singleton(ModuleRegistryInterface::class, function () {
            return new ModuleRegistry();
        });

        $this->app->alias(ModuleRegistryInterface::class, 'module.registry');
        $this->app->alias(ModuleRegistryInterface::class, ModuleRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
