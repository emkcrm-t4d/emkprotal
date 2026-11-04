<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Framework Service Providers
    |--------------------------------------------------------------------------
    |
    | Core framework service providers bootstrap foundational Laravel facilities.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Domain Service Providers
    |--------------------------------------------------------------------------
    |
    | Modular domain service providers (e.g., Workspace, Lead, Member).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Third-Party & Infrastructure Service Providers
    |--------------------------------------------------------------------------
    |
    | Package providers required for Livewire, Spatie RBAC, Horizon, etc.
    |
    */
    App\Providers\HorizonServiceProvider::class,

    /*
    |--------------------------------------------------------------------------
    | Application Service Providers
    |--------------------------------------------------------------------------
    |
    | Top-level application service providers.
    |
    */
    App\Providers\AppServiceProvider::class,
];
