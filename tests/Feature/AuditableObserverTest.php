<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Contracts\AuditableInterface;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Observers\AuditableObserver;
use App\Domain\Audit\Traits\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TestAuditableStub extends Model implements AuditableInterface
{
    use Auditable;

    protected $table = 'test_auditable_stubs';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}

class AuditableObserverTest extends TestCase
{
    use RefreshDatabase;

    protected AuditableObserver $observer;
    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed space and workspace
        DB::table('spaces')->insert([
            'id' => 1,
            'code' => 'EMK-DHK',
            'name' => 'EMK Center Dhaka',
            'category' => 'Center',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspaces')->insert([
            'id' => 'ws-emk-dhaka',
            'space_id' => 1,
            'name' => 'EMK Center Dhaka',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Setup temporary table for thorough auditable testing
        Schema::create('test_auditable_stubs', function (Blueprint $table) {
            $table->id();
            $table->string('workspace_id', 50)->nullable();
            $table->string('name');
            $table->string('api_key')->nullable();
            $table->string('secret_token')->nullable();
            $table->string('bkash_pin')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $this->observer = new AuditableObserver();

        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'manager@emk.test',
            'password' => 'plaintext-super-secret-12345',
            'guard' => 'web',
            'is_active' => true,
        ]);

        TestAuditableStub::observe(AuditableObserver::class);
        User::observe(AuditableObserver::class);
    }

    public function test_redact_sensitive_data_scrubs_all_sensitive_keys_and_nested_payloads(): void
    {
        $input = [
            'name' => 'Public Name',
            'password' => 'PlainTextSecret123!',
            'password_hash' => '$2y$12$e8xL..hashedPassword',
            'remember_token' => 'randomRememberTokenString',
            'secret' => 'oauth_client_secret',
            'api_key' => 'live_sk_1234567890',
            'token' => 'jwt.token.here',
            'bearer' => 'Bearer eyJhbGciOi...',
            'bkash_pin' => '12345',
            'private_key' => '-----BEGIN RSA PRIVATE KEY-----',
            'authorization' => 'Basic dXNlcjpwYXNz',
            'pin' => '9999',
            'access_token' => 'at_987654321',
            'refresh_token' => 'rt_123456789',
            'metadata' => [
                'webhook_secret' => 'whsec_99999',
                'nested' => [
                    'apiKey' => 'nested_key_xyz',
                ],
                'safe_info' => 'Not Secret',
            ],
            'safe_field' => 'Safe Value',
        ];

        $redacted = $this->observer->redactSensitiveData($input);

        $this->assertSame('Public Name', $redacted['name']);
        $this->assertSame('Safe Value', $redacted['safe_field']);
        $this->assertSame('Not Secret', $redacted['metadata']['safe_info']);

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['password_hash']);
        $this->assertSame('[REDACTED]', $redacted['remember_token']);
        $this->assertSame('[REDACTED]', $redacted['secret']);
        $this->assertSame('[REDACTED]', $redacted['api_key']);
        $this->assertSame('[REDACTED]', $redacted['token']);
        $this->assertSame('[REDACTED]', $redacted['bearer']);
        $this->assertSame('[REDACTED]', $redacted['bkash_pin']);
        $this->assertSame('[REDACTED]', $redacted['private_key']);
        $this->assertSame('[REDACTED]', $redacted['authorization']);
        $this->assertSame('[REDACTED]', $redacted['pin']);
        $this->assertSame('[REDACTED]', $redacted['access_token']);
        $this->assertSame('[REDACTED]', $redacted['refresh_token']);
        $this->assertSame('[REDACTED]', $redacted['metadata']['webhook_secret']);
        $this->assertSame('[REDACTED]', $redacted['metadata']['nested']['apiKey']);
    }

    public function test_creating_auditable_model_inserts_audit_log_with_redacted_secrets(): void
    {
        $this->actingAs($this->manager);

        $stub = TestAuditableStub::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Payment Gateway Config',
            'api_key' => 'sk_live_9988776655',
            'secret_token' => 'secret_live_token',
            'bkash_pin' => '4321',
            'metadata' => ['bkash_pin' => '4321', 'note' => 'Store config'],
        ]);

        $log = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', TestAuditableStub::class)
            ->where('auditable_id', $stub->id)
            ->where('event_type', AuditLog::EVENT_CREATED)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('ws-emk-dhaka', $log->workspace_id);
        $this->assertSame($this->manager->id, $log->actor_id);
        $this->assertNull($log->old_values);

        $newValues = $log->new_values;
        $this->assertSame('Payment Gateway Config', $newValues['name']);
        $this->assertSame('[REDACTED]', $newValues['api_key']);
        $this->assertSame('[REDACTED]', $newValues['secret_token']);
        $this->assertSame('[REDACTED]', $newValues['bkash_pin']);
        $this->assertSame('[REDACTED]', $newValues['metadata']['bkash_pin']);
        $this->assertSame('Store config', $newValues['metadata']['note']);

        // Assert plaintext secrets are absent from audit_logs table
        $rawRow = DB::table('audit_logs')->where('id', $log->id)->first();
        $this->assertStringNotContainsString('sk_live_9988776655', json_encode($rawRow));
        $this->assertStringNotContainsString('secret_live_token', json_encode($rawRow));
        $this->assertStringNotContainsString('4321', json_encode($rawRow));
    }

    public function test_updating_auditable_model_records_both_old_and_new_redacted_values(): void
    {
        $this->actingAs($this->manager);

        $stub = TestAuditableStub::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Initial Name',
            'api_key' => 'old_secret_key_111',
            'secret_token' => 'old_token_value',
            'bkash_pin' => '1111',
        ]);

        $stub->update([
            'name' => 'Updated Name',
            'api_key' => 'new_secret_key_222',
            'bkash_pin' => '2222',
        ]);

        $log = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', TestAuditableStub::class)
            ->where('auditable_id', $stub->id)
            ->where('event_type', AuditLog::EVENT_UPDATED)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Initial Name', $log->old_values['name']);
        $this->assertSame('Updated Name', $log->new_values['name']);
        $this->assertSame('[REDACTED]', $log->old_values['api_key']);
        $this->assertSame('[REDACTED]', $log->new_values['api_key']);
        $this->assertSame('[REDACTED]', $log->old_values['bkash_pin']);
        $this->assertSame('[REDACTED]', $log->new_values['bkash_pin']);

        // Plaintext keys must never exist in audit_logs row
        $rawRow = DB::table('audit_logs')->where('id', $log->id)->first();
        $this->assertStringNotContainsString('old_secret_key_111', json_encode($rawRow));
        $this->assertStringNotContainsString('new_secret_key_222', json_encode($rawRow));
        $this->assertStringNotContainsString('1111', json_encode($rawRow));
        $this->assertStringNotContainsString('2222', json_encode($rawRow));
    }

    public function test_deleting_auditable_model_records_deleted_event_with_redacted_old_values(): void
    {
        $this->actingAs($this->manager);

        $stub = TestAuditableStub::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'To Be Deleted',
            'api_key' => 'key_to_delete',
        ]);

        $stub->delete();

        $log = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', TestAuditableStub::class)
            ->where('auditable_id', $stub->id)
            ->where('event_type', AuditLog::EVENT_DELETED)
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->new_values);
        $this->assertSame('To Be Deleted', $log->old_values['name']);
        $this->assertSame('[REDACTED]', $log->old_values['api_key']);
    }

    public function test_user_model_password_is_never_stored_in_audit_logs(): void
    {
        $this->actingAs($this->manager);

        $newUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Audited Staff',
            'email' => 'audited.staff@emk.test',
            'password' => 'PlaintextPasswordToBeScrubbed12345!',
            'guard' => 'web',
            'is_active' => true,
        ]);

        $log = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $newUser->id)
            ->where('event_type', AuditLog::EVENT_CREATED)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('[REDACTED]', $log->new_values['password']);

        // Inspect raw database content for the audit log
        $rawLog = DB::table('audit_logs')->where('id', $log->id)->first();
        $this->assertStringNotContainsString('PlaintextPasswordToBeScrubbed12345!', json_encode($rawLog));
    }
}
