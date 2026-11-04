<?php

declare(strict_types=1);

arch('strictly prohibit dynamic code evaluation across application')
    ->expect(['eval', 'create_function'])
    ->not->toBeUsed();

arch('enforce domain registry encapsulation')
    ->expect('App\\Domain')
    ->toOnlyUse([
        'Illuminate',
        'App\Domain',
        'App\Models',
        'App\Mail',
        'App\Jobs',
        'Spatie\Permission',
        'Carbon',
        'Ramsey\Uuid',
        'Symfony\Component\HttpFoundation\StreamedResponse',
        'app',
        'auth',
        'config',
        'e',
        'event',
        'now',
        'request',
    ]);

arch('controllers and livewire do not use dynamic evaluation')
    ->expect(['App\Http\Controllers', 'App\Livewire'])
    ->not->toUse(['eval', 'create_function']);
