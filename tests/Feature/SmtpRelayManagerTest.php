<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Mail\Models\SmtpRelay;
use App\Domain\Mail\Services\SmtpTransportManager;
use App\Livewire\Mail\SmtpRelayManager;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class SmtpRelayManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::where('email', 'admin@emk.test')->firstOrFail();
    }

    public function test_unauthenticated_user_cannot_access_smtp_settings(): void
    {
        $response = $this->get('/crm/ws-emk-dhaka/settings/smtp');
        $response->assertRedirect('/login');
    }

    public function test_authorized_admin_can_access_smtp_module(): void
    {
        $this->actingAs($this->adminUser, 'web');

        $response = $this->get('/crm/ws-emk-dhaka/settings/smtp');
        $response->assertStatus(200);
        $response->assertSee('Email SMTP Module');
        $response->assertSee('Add New SMTP Relay');
    }

    public function test_admin_can_create_new_smtp_relay(): void
    {
        $this->actingAs($this->adminUser, 'web');

        Livewire::test(SmtpRelayManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openCreateModal')
            ->set('name', 'Brevo Production Relay')
            ->set('priority', 1)
            ->set('sender_email', 'noreply@jaago.com.bd')
            ->set('sender_name', 'JAAGO HUB')
            ->set('host', 'smtp-relay.brevo.com')
            ->set('port', 587)
            ->set('encryption', 'starttls')
            ->set('username', 'nasif.kamal@jaago.com.bd')
            ->set('password', 'secret-smtp-password-123')
            ->set('throttle_seconds', 0)
            ->set('reply_to', 'pnc@jaago.com.bd')
            ->set('is_active', true)
            ->call('saveRelay');

        $this->assertDatabaseHas('smtp_relays', [
            'name' => 'Brevo Production Relay',
            'workspace_id' => 'ws-emk-dhaka',
            'sender_email' => 'noreply@jaago.com.bd',
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'encryption' => 'starttls',
            'username' => 'nasif.kamal@jaago.com.bd',
            'priority' => 1,
            'is_active' => true,
        ]);

        $created = SmtpRelay::where('name', 'Brevo Production Relay')->firstOrFail();
        $this->assertSame('secret-smtp-password-123', $created->password);
        $rawPassword = $created->getRawOriginal('password');
        $this->assertNotSame('secret-smtp-password-123', $rawPassword);
    }

    public function test_admin_can_update_existing_smtp_relay(): void
    {
        $this->actingAs($this->adminUser, 'web');

        $relay = SmtpRelay::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Old Server Name',
            'priority' => 2,
            'sender_email' => 'old@emkcenter.org',
            'sender_name' => 'EMK Old',
            'host' => 'old-smtp.server.com',
            'port' => 25,
            'encryption' => 'none',
            'username' => 'old_user',
            'password' => 'old-password',
            'is_active' => true,
        ]);

        Livewire::test(SmtpRelayManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('editRelay', $relay->id)
            ->set('name', 'Updated Brevo Secondary')
            ->set('port', 587)
            ->set('encryption', 'starttls')
            ->call('saveRelay');

        $this->assertDatabaseHas('smtp_relays', [
            'id' => $relay->id,
            'name' => 'Updated Brevo Secondary',
            'port' => 587,
            'encryption' => 'starttls',
        ]);
    }

    public function test_admin_can_toggle_relay_active_state(): void
    {
        $this->actingAs($this->adminUser, 'web');

        $relay = SmtpRelay::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Toggle Relay',
            'priority' => 1,
            'sender_email' => 'toggle@emkcenter.org',
            'sender_name' => 'EMK Toggle',
            'host' => 'smtp.test.com',
            'port' => 587,
            'encryption' => 'starttls',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
        ]);

        Livewire::test(SmtpRelayManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('toggleActive', $relay->id);

        $this->assertFalse((bool) $relay->fresh()->is_active);

        Livewire::test(SmtpRelayManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('toggleActive', $relay->id);

        $this->assertTrue((bool) $relay->fresh()->is_active);
    }

    public function test_admin_can_delete_relay(): void
    {
        $this->actingAs($this->adminUser, 'web');

        $relay = SmtpRelay::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Relay To Delete',
            'priority' => 1,
            'sender_email' => 'delete@emkcenter.org',
            'sender_name' => 'EMK',
            'host' => 'smtp.test.com',
            'port' => 587,
            'encryption' => 'starttls',
            'username' => 'user',
            'password' => 'pass',
            'is_active' => true,
        ]);

        Livewire::test(SmtpRelayManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('deleteRelay', $relay->id);

        $this->assertDatabaseMissing('smtp_relays', [
            'id' => $relay->id,
        ]);
    }

    public function test_broken_relay_surfaces_red_alert_banner(): void
    {
        $this->actingAs($this->adminUser, 'web');

        SmtpRelay::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Broken Brevo Gateway',
            'priority' => 1,
            'sender_email' => 'noreply@jaago.com.bd',
            'sender_name' => 'JAAGO HUB',
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'encryption' => 'starttls',
            'username' => 'nasif.kamal@jaago.com.bd',
            'password' => 'invalid-key',
            'is_active' => true,
            'status' => 'error',
            'last_error_message' => '535 5.7.8 Authentication failed',
            'last_tested_at' => now(),
        ]);

        $manager = app(SmtpTransportManager::class);
        $this->assertTrue($manager->hasBrokenConnection('ws-emk-dhaka'));

        Livewire::test(SmtpRelayManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSee('SMTP Connection Alert:')
            ->assertSee('Authentication Rejected')
            ->assertSee('Error 535');
    }

    public function test_email_activity_log_tracks_sent_and_failed_messages(): void
    {
        $this->actingAs($this->adminUser, 'web');

        $t1 = \App\Domain\Mail\Models\EmailThread::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'participant_email' => 'member.sent@example.com',
            'subject' => 'Application Receipt Confirmation',
            'message_count' => 1,
        ]);

        $sentMsg = \App\Domain\Mail\Models\EmailMessage::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $t1->id,
            'direction' => 'outbound',
            'status' => 'sent',
            'from_email' => 'noreply@jaago.com.bd',
            'to_email' => 'member.sent@example.com',
            'subject' => 'Application Receipt Confirmation',
            'template_key' => 'application_receipt',
            'sent_at' => now(),
        ]);

        $t2 = \App\Domain\Mail\Models\EmailThread::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'participant_email' => 'member.failed@example.com',
            'subject' => 'Payment Receipt Notice',
            'message_count' => 1,
        ]);

        $failedMsg = \App\Domain\Mail\Models\EmailMessage::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'email_thread_id' => $t2->id,
            'direction' => 'outbound',
            'status' => 'failed',
            'from_email' => 'noreply@jaago.com.bd',
            'to_email' => 'member.failed@example.com',
            'subject' => 'Payment Receipt Notice',
            'template_key' => 'conversion_confirmation',
            'failure_reason' => '535 5.7.8 Authentication failed on Brevo server',
        ]);

        $component = Livewire::test(SmtpRelayManager::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSee('Outbound Email Activity & Delivery Log')
            ->assertSee('member.sent@example.com')
            ->assertSee('member.failed@example.com')
            ->assertSee('Sent')
            ->assertSee('Failed');

        // Test filtering only failed
        $component->set('logFilter', 'failed')
            ->assertSee('member.failed@example.com')
            ->assertDontSee('member.sent@example.com');

        // Test filtering only sent
        $component->set('logFilter', 'sent')
            ->assertSee('member.sent@example.com')
            ->assertDontSee('member.failed@example.com');

        // Test retry action on failed email
        $component->call('retryEmail', $failedMsg->id);
        $this->assertContains($failedMsg->fresh()->status, ['queued', 'sent']);
    }

    public function test_smtp_priority_ordering_and_workspace_fallback(): void
    {
        // Global fallback relay
        SmtpRelay::create([
            'workspace_id' => null,
            'name' => 'Global Fallback Relay',
            'priority' => 1,
            'sender_email' => 'global@emkcenter.org',
            'sender_name' => 'Global EMK',
            'host' => 'global.smtp.com',
            'port' => 587,
            'encryption' => 'starttls',
            'username' => 'global_user',
            'password' => 'global_pass',
            'is_active' => true,
        ]);

        $manager = app(SmtpTransportManager::class);

        // When workspace has no relays of its own, fallback to global
        $relays = $manager->getActiveRelaysForWorkspace('ws-emk-dhaka');
        $this->assertCount(1, $relays);
        $this->assertSame('Global Fallback Relay', $relays->first()->name);

        // When workspace has its own relays, they take precedence
        SmtpRelay::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Specific Workspace Relay',
            'priority' => 1,
            'sender_email' => 'specific@emkcenter.org',
            'sender_name' => 'Specific EMK',
            'host' => 'specific.smtp.com',
            'port' => 587,
            'encryption' => 'starttls',
            'username' => 'specific_user',
            'password' => 'specific_pass',
            'is_active' => true,
        ]);

        $relaysSpecific = $manager->getActiveRelaysForWorkspace('ws-emk-dhaka');
        $this->assertCount(1, $relaysSpecific);
        $this->assertSame('Specific Workspace Relay', $relaysSpecific->first()->name);
    }
}
