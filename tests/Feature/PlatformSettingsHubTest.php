<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Platform\PlatformSettingsHub;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformSettingsHubTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Workspace $otherWorkspace;
    protected User $manager;
    protected User $unauthorizedStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->workspace = Workspace::firstOrCreate(
            ['id' => 'ws-emk-dhaka'],
            [
                'space_id' => 1,
                'name' => 'EMK Center Dhaka',
                'is_active' => true,
            ]
        );

        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        $this->otherWorkspace = Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'American Corner Chattogram',
                'is_active' => true,
            ]
        );

        // 1. Workspace Manager (possesses platform_services.manage_settings)
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Platform Settings Manager',
            'email' => 'manager.settings@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Delegated Staff (lacks platform_services.manage_settings)
        $this->unauthorizedStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Front Desk Staff',
            'email' => 'staff.unauthorized@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->unauthorizedStaff->assignRole('delegated_staff');
    }

    public function test_workspace_manager_can_access_settings_hub_and_view_parameters(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('crm.settings.index', $this->workspace->id));

        $response->assertStatus(200);
        $response->assertSee('Platform Settings Hub');
        $response->assertSee('ws-emk-dhaka');
        $response->assertSee('System Parameters');
        $response->assertSee('Membership Terms');
        $response->assertSee('Staff & Delegations', false);
        $response->assertSee('Email Templates');
        $response->assertSee('Delivery Monitor');
        $response->assertSee('Workflow Rules');
        $response->assertSee('Audit Logs');

        // Verify Livewire component renders parameters
        Livewire::test(PlatformSettingsHub::class, ['workspace' => $this->workspace->id])
            ->assertStatus(200)
            ->assertSee('EMK Center')
            ->assertSee('info@emkcenter.org');
    }

    public function test_unauthorized_delegated_staff_receives_http_403_forbidden(): void
    {
        $this->actingAs($this->unauthorizedStaff);

        // 1. Route access is rejected with 403
        $response = $this->get(route('crm.settings.index', $this->workspace->id));
        $response->assertStatus(403);

        // 2. Direct Livewire component mount is rejected with 403
        Livewire::test(PlatformSettingsHub::class, ['workspace' => $this->workspace->id])
            ->assertStatus(403);
    }

    public function test_cross_workspace_request_is_blocked_with_http_403(): void
    {
        $this->actingAs($this->manager); // manager belongs to ws-emk-dhaka

        // Attempting to access ws-chattogram settings
        $response = $this->get(route('crm.settings.index', $this->otherWorkspace->id));
        $response->assertStatus(403);

        Livewire::test(PlatformSettingsHub::class, ['workspace' => $this->otherWorkspace->id])
            ->assertStatus(403);
    }

    public function test_platform_services_route_redirects_to_settings(): void
    {
        $this->actingAs($this->manager);

        $response = $this->get(route('crm.platform_services', $this->workspace->id));

        $response->assertRedirect(route('crm.settings.index', $this->workspace->id));
    }

    public function test_tab_switching_works(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(PlatformSettingsHub::class, ['workspace' => $this->workspace->id])
            ->assertSet('activeTab', 'general')
            ->call('switchTab', 'membership')
            ->assertSet('activeTab', 'membership')
            ->call('switchTab', 'payments')
            ->assertSet('activeTab', 'payments')
            ->call('switchTab', 'security')
            ->assertSet('activeTab', 'security');
    }

    public function test_saving_parameters_persists_modifications_and_emits_audit_log(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(PlatformSettingsHub::class, ['workspace' => $this->workspace->id])
            ->set('settings.general.organization_name', 'EMK Center Hub (Updated)')
            ->set('settings.general.contact_email', 'contact.updated@emkcenter.org')
            ->set('settings.general.default_locale', 'bn')
            ->set('settings.membership.renewal_window_days', 45)
            ->set('settings.payments.manual_bkash_merchant_number', '01899999999')
            ->set('settings.payments.bkash_secret', 'new_bKash_live_secret_456')
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertSee('Platform settings updated successfully.');

        // 1. Verify in database
        $this->assertSame(
            'EMK Center Hub (Updated)',
            PlatformSetting::where('workspace_id', $this->workspace->id)
                ->where('key', 'general.organization_name')
                ->first()?->value
        );

        $this->assertSame(
            45,
            PlatformSetting::where('workspace_id', $this->workspace->id)
                ->where('key', 'membership.renewal_window_days')
                ->first()?->value
        );

        // 2. Verify encrypted secret in database
        $secretSetting = PlatformSetting::where('workspace_id', $this->workspace->id)
            ->where('key', 'payments.bkash_secret')
            ->first();

        $this->assertNotNull($secretSetting);
        $this->assertSame('new_bKash_live_secret_456', $secretSetting->value);
        $this->assertSame('••••••••', $secretSetting->masked_value);

        $rawDbValue = DB::table('platform_settings')
            ->where('id', $secretSetting->id)
            ->value('value');
        $this->assertNotSame('new_bKash_live_secret_456', $rawDbValue);
        $this->assertSame('new_bKash_live_secret_456', Crypt::decryptString($rawDbValue));

        // 3. Verify audit log entry and redaction
        $auditLog = AuditLog::where('auditable_id', $secretSetting->id)
            ->where('event_type', AuditLog::EVENT_PLATFORM_SETTING_UPDATED)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('[REDACTED]', $auditLog->new_values['value']);
        $this->assertNotSame('new_bKash_live_secret_456', $auditLog->new_values['value']);
    }

    public function test_validation_fails_on_invalid_parameters(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(PlatformSettingsHub::class, ['workspace' => $this->workspace->id])
            ->set('settings.general.organization_name', '') // required
            ->set('settings.general.contact_email', 'invalid-email-address') // must be email
            ->set('settings.membership.renewal_window_days', -5) // must be min:1
            ->call('saveSettings')
            ->assertHasErrors([
                'settings.general.organization_name',
                'settings.general.contact_email',
                'settings.membership.renewal_window_days',
            ]);
    }
}
