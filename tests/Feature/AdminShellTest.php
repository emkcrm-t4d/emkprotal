<?php

declare(strict_types=1);

use App\Domain\Platform\Registry\ModuleRegistryInterface;
use App\Livewire\Admin\AdminShell;
use Illuminate\Support\HtmlString;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

test('admin shell livewire component renders workspace header and core modules', function () {
    Livewire::test(AdminShell::class)
        ->assertStatus(200)
        ->assertSee('EMK Center Dhaka')
        ->assertSee('ws-emk-dhaka')
        ->assertSee('Active')
        ->assertSee('guard: web')
        ->assertSee('Core Modules')
        ->assertSee('leads')
        ->assertSee('members')
        ->assertSee('forms')
        ->assertSee('invoices')
        ->assertSee('dashboard_reports')
        ->assertSee('platform_services');
});

test('admin shell layout renders successfully with slot content', function () {
    $view = $this->view('layouts.admin', [
        'slot' => new HtmlString('<div id="test-admin-content">Hello Admin Portal</div>'),
    ]);

    $view->assertSee('Hello Admin Portal', false)
        ->assertSee('csrf-token')
        ->assertSee('EMK Center Dhaka');
});
