<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\StaffLogin;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_page_renders_successfully_with_staff_login_component(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSeeLivewire(StaffLogin::class);
        $response->assertSee('Staff & Admin Sign In');
        $response->assertSee('admin@emk.test');
    }

    public function test_staff_login_rejects_invalid_credentials(): void
    {
        Livewire::test(StaffLogin::class)
            ->set('email', 'admin@emk.test')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_superadmin_can_login_with_default_credentials(): void
    {
        Livewire::test(StaffLogin::class)
            ->set('email', 'admin@emk.test')
            ->set('password', 'secret123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('crm.dashboard', 'ws-emk-dhaka'));

        $this->assertTrue(Auth::guard('web')->check());
        $this->assertSame('admin@emk.test', Auth::guard('web')->user()->email);
    }

    public function test_workspace_manager_can_login_and_redirect_to_dashboard(): void
    {
        Livewire::test(StaffLogin::class)
            ->set('email', 'manager.dhaka@emk.test')
            ->set('password', 'secret123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('crm.dashboard', 'ws-emk-dhaka'));

        $this->assertTrue(Auth::guard('web')->check());
        $this->assertSame('manager.dhaka@emk.test', Auth::guard('web')->user()->email);
    }

    public function test_dashboard_redirects_authenticated_user_to_workspace_dashboard(): void
    {
        $user = User::where('email', 'admin@emk.test')->first();
        $this->actingAs($user, 'web');

        $response = $this->get('/dashboard');
        $response->assertRedirect(route('crm.dashboard', 'ws-emk-dhaka'));
    }

    public function test_staff_can_logout_and_redirect_to_login(): void
    {
        $user = User::where('email', 'admin@emk.test')->first();
        $this->actingAs($user, 'web');

        $response = $this->get('/logout');
        $response->assertRedirect(route('login'));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_authenticated_admin_can_view_operational_dashboard(): void
    {
        $user = User::where('email', 'admin@emk.test')->first();
        $this->actingAs($user, 'web');

        $response = $this->get('/crm/ws-emk-dhaka/dashboard');
        $response->assertStatus(200);
        $response->assertSeeLivewire(\App\Livewire\Reports\OperationalDashboard::class);
        $response->assertSee('Operational Metrics');
    }
}

