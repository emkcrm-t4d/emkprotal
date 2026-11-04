<?php

declare(strict_types=1);

arch('application code strictly forbids withoutGlobalScope')
    ->expect(['App\Http', 'App\Domain', 'App\Livewire'])
    ->not->toUse('withoutGlobalScope')
    ->not->toUse('withoutGlobalScopes');

arch('tenant domain models implement TenantEntityInterface and use BelongsToTenant')
    ->expect([
        \App\Domain\Leads\Models\Lead::class,
        \App\Domain\Leads\Models\LeadAttachment::class,
    ])
    ->toImplement(\App\Domain\Tenancy\Contracts\TenantEntityInterface::class)
    ->toUse(\App\Domain\Tenancy\Traits\BelongsToTenant::class);
