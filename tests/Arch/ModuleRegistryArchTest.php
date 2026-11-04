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
        'Spatie\Permission',
        'Carbon\Carbon',
        'Ramsey\Uuid',
    ]);

arch('controllers and livewire do not use dynamic evaluation')
    ->expect(['App\Http\Controllers', 'App\Livewire'])
    ->not->toUse(['eval', 'create_function']);
