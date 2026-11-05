<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Platform\Services\PlatformSettingsService;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformSettingsService $service;
    protected Workspace $workspace;
    protected User $manager;

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

        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Settings Manager',
            'email' => 'manager.settings@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        $this->service = app(PlatformSettingsService::class);
    }

    public function test_get_and_set_across_all_cast_types(): void
    {
        // 1. String
        $this->service->set('general.test_string', 'Hello World', null, 'general', 'string');
        $this->assertSame('Hello World', $this->service->get('general.test_string'));

        // 2. Integer
        $this->service->set('membership.test_int', 42, null, 'membership', 'integer');
        $this->assertSame(42, $this->service->get('membership.test_int'));

        // 3. Boolean
        $this->service->set('security.test_bool_true', true, null, 'security', 'boolean');
        $this->assertTrue($this->service->get('security.test_bool_true'));

        $this->service->set('security.test_bool_false', false, null, 'security', 'boolean');
        $this->assertFalse($this->service->get('security.test_bool_false'));

        // 4. JSON
        $data = ['timeout' => 300, 'retries' => 3, 'features' => ['a', 'b']];
        $this->service->set('general.test_json', $data, null, 'general', 'json');
        $this->assertSame($data, $this->service->get('general.test_json'));

        // 5. Encrypted
        $secretValue = 'SuperSecretToken_98765';
        $this->service->set('payments.test_encrypted', $secretValue, null, 'payments', 'encrypted', true);
        $this->assertSame($secretValue, $this->service->get('payments.test_encrypted'));
    }

    public function test_workspace_specific_override_takes_precedence_over_global_setting(): void
    {
        // Global setting
        $this->service->set('general.org_name', 'Global EMK Center', null, 'general', 'string');

        // Workspace-specific override
        $this->service->set('general.org_name', 'Dhaka Hub Center', 'ws-emk-dhaka', 'general', 'string');

        // Different workspace without override
        $otherWs = Workspace::create([
            'id' => 'ws-sylhet',
            'space_id' => 1,
            'name' => 'Sylhet Corner',
            'is_active' => true,
        ]);

        // Assert ws-emk-dhaka gets its specific override
        $this->assertSame('Dhaka Hub Center', $this->service->get('general.org_name', 'ws-emk-dhaka'));

        // Assert ws-sylhet falls back to global setting
        $this->assertSame('Global EMK Center', $this->service->get('general.org_name', 'ws-sylhet'));

        // Assert global lookup returns global setting
        $this->assertSame('Global EMK Center', $this->service->get('general.org_name', null));

        // Assert non-existent key returns fallback default
        $this->assertSame('DefaultFallback', $this->service->get('non.existent.key', 'ws-emk-dhaka', 'DefaultFallback'));
    }

    public function test_secret_setting_is_encrypted_at_rest_and_masked_in_presentation(): void
    {
        $plainSecret = 'merchant_secret_key_abc123';

        $setting = $this->service->set(
            key: 'payments.bkash_secret',
            value: $plainSecret,
            workspaceId: 'ws-emk-dhaka',
            group: 'payments',
            castType: 'encrypted',
            isSecret: true,
            description: 'bKash merchant API secret'
        );

        // 1. Assert raw database column does NOT store plaintext
        $rawInDb = DB::table('platform_settings')
            ->where('id', $setting->id)
            ->value('value');

        $this->assertNotSame($plainSecret, $rawInDb);
        $this->assertSame($plainSecret, Crypt::decryptString($rawInDb));

        // 2. Assert model value getter decrypts correctly
        $this->assertSame($plainSecret, $setting->value);

        // 3. Assert presentation masked value returns bullets per T-10-08
        $this->assertSame('••••••••', $setting->masked_value);
    }

    public function test_updating_secret_parameter_scrubs_values_in_audit_logs(): void
    {
        $this->actingAs($this->manager);

        $initialSecret = 'secret_v1_abc';
        $updatedSecret = 'secret_v2_xyz';

        // 1. Initial creation
        $setting = $this->service->set(
            key: 'payments.webhook_secret',
            value: $initialSecret,
            workspaceId: 'ws-emk-dhaka',
            group: 'payments',
            castType: 'encrypted',
            isSecret: true
        );

        $createLog = AuditLog::where('auditable_id', $setting->id)
            ->where('event_type', AuditLog::EVENT_PLATFORM_SETTING_UPDATED)
            ->latest('id')
            ->first();

        $this->assertNotNull($createLog);
        $this->assertSame('[REDACTED]', $createLog->new_values['value']);
        $this->assertNotSame($initialSecret, $createLog->new_values['value']);

        // 2. Update to new secret
        $this->service->set(
            key: 'payments.webhook_secret',
            value: $updatedSecret,
            workspaceId: 'ws-emk-dhaka',
            group: 'payments',
            castType: 'encrypted',
            isSecret: true
        );

        $updateLog = AuditLog::where('auditable_id', $setting->id)
            ->where('event_type', AuditLog::EVENT_PLATFORM_SETTING_UPDATED)
            ->latest('id')
            ->first();

        $this->assertNotNull($updateLog);
        $this->assertSame('[REDACTED]', $updateLog->old_values['value']);
        $this->assertSame('[REDACTED]', $updateLog->new_values['value']);
        $this->assertNotSame($initialSecret, $updateLog->old_values['value']);
        $this->assertNotSame($updatedSecret, $updateLog->new_values['value']);
    }

    public function test_get_group_merges_global_defaults_with_workspace_overrides(): void
    {
        // Setup global group settings
        $this->service->set('membership.term', 'GLOBAL_TERM', null, 'membership', 'string');
        $this->service->set('membership.renewal_days', 30, null, 'membership', 'integer');
        $this->service->set('membership.reversal_days', 15, null, 'membership', 'integer');

        // Workspace overrides only one parameter
        $this->service->set('membership.renewal_days', 45, 'ws-emk-dhaka', 'membership', 'integer');

        $resolvedGroup = $this->service->getGroup('membership', 'ws-emk-dhaka');

        $this->assertSame('GLOBAL_TERM', $resolvedGroup['membership.term']);
        $this->assertSame(45, $resolvedGroup['membership.renewal_days']); // overridden
        $this->assertSame(15, $resolvedGroup['membership.reversal_days']); // inherited global
    }

    public function test_seed_defaults_creates_baseline_parameters_without_overwriting(): void
    {
        // Pre-create custom org name
        $this->service->set('general.organization_name', 'Custom EMK Hub', null, 'general', 'string');

        // Run seedDefaults
        $this->service->seedDefaults();

        // Custom org name was preserved
        $this->assertSame('Custom EMK Hub', $this->service->get('general.organization_name'));

        // Baseline defaults populated
        $this->assertSame('info@emkcenter.org', $this->service->get('general.contact_email'));
        $this->assertSame('STANDARD_1YR', $this->service->get('membership.default_term_code'));
        $this->assertSame(30, $this->service->get('membership.renewal_window_days'));
        $this->assertSame(30, $this->service->get('membership.max_reversals_allowed_days'));
        $this->assertSame('01700000000', $this->service->get('payments.manual_bkash_merchant_number'));
        $this->assertSame('500.00', $this->service->get('payments.default_application_fee_bdt'));
        $this->assertSame(120, $this->service->get('security.session_timeout_minutes'));
        $this->assertSame(90, $this->service->get('security.require_password_reset_days'));
    }
}
