<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Domain\Platform\Services\PlatformSettingsService;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.admin')]
class PlatformSettingsHub extends Component
{
    public string $workspaceId = '';
    public string $workspace = '';

    #[Url(as: 'tab', except: 'general')]
    public string $activeTab = 'general';

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $settings = [];

    /**
     * Mount component asserting non-delegable authorization and workspace tenancy.
     */
    public function mount(mixed $workspace): void
    {
        $this->workspace = $workspace instanceof Workspace
            ? (string) $workspace->id
            : (string) $workspace;

        $this->workspaceId = $this->workspace;

        $this->authorizeAccess();

        $this->loadSettings();
    }

    /**
     * Assert authorization and tenancy access per DASH-04 and T-10-07.
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole')
            && $user->hasRole(['superadmin', 'global_admin']);

        // Assert staff belongs to target workspace
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspaceId) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Strict non-delegable permission check per T-10-07
        $canManage = $user->can('platform_services.manage_settings') || $isElevatedAdmin;

        if (!$canManage) {
            App::abort(403, 'Forbidden: platform_services.manage_settings permission required.');
        }
    }

    /**
     * Populate settings from storage engine with defaults and secret masking.
     */
    public function loadSettings(): void
    {
        /** @var PlatformSettingsService $service */
        $service = app(PlatformSettingsService::class);

        // Ensure baseline defaults exist
        $service->seedDefaults();

        $this->settings = [
            'general' => [
                'organization_name' => (string) $service->get('general.organization_name', $this->workspaceId, 'EMK Center'),
                'contact_email' => (string) $service->get('general.contact_email', $this->workspaceId, 'info@emkcenter.org'),
                'default_locale' => (string) $service->get('general.default_locale', $this->workspaceId, 'en'),
            ],
            'membership' => [
                'renewal_window_days' => (int) $service->get('membership.renewal_window_days', $this->workspaceId, 30),
                'max_reversals_allowed_days' => (int) $service->get('membership.max_reversals_allowed_days', $this->workspaceId, 30),
                'default_term_code' => (string) $service->get('membership.default_term_code', $this->workspaceId, 'STANDARD_1YR'),
            ],
            'payments' => [
                'manual_bkash_merchant_number' => (string) $service->get('payments.manual_bkash_merchant_number', $this->workspaceId, '01700000000'),
                'default_application_fee_bdt' => (string) $service->get('payments.default_application_fee_bdt', $this->workspaceId, '500.00'),
                'instruction_text' => (string) $service->get('payments.instruction_text', $this->workspaceId, 'Send application fee to merchant bKash number and keep TrxID.'),
                'bkash_secret' => '••••••••',
            ],
            'security' => [
                'session_timeout_minutes' => (int) $service->get('security.session_timeout_minutes', $this->workspaceId, 120),
                'require_password_reset_days' => (int) $service->get('security.require_password_reset_days', $this->workspaceId, 90),
            ],
        ];
    }

    /**
     * Switch active parameter tab.
     */
    public function switchTab(string $tab): void
    {
        if (in_array($tab, ['general', 'membership', 'payments', 'security'], true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Persist parameter modifications via PlatformSettingsService with audit logging.
     */
    public function saveSettings(): void
    {
        $this->authorizeAccess();

        $this->validate([
            'settings.general.organization_name' => ['required', 'string', 'max:100'],
            'settings.general.contact_email' => ['required', 'email', 'max:150'],
            'settings.general.default_locale' => ['required', 'string', 'in:en,bn'],
            'settings.membership.renewal_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'settings.membership.max_reversals_allowed_days' => ['required', 'integer', 'min:1', 'max:365'],
            'settings.membership.default_term_code' => ['required', 'string', 'max:50'],
            'settings.payments.manual_bkash_merchant_number' => ['required', 'string', 'max:30'],
            'settings.payments.default_application_fee_bdt' => ['required', 'numeric', 'min:0'],
            'settings.payments.instruction_text' => ['nullable', 'string', 'max:1000'],
            'settings.payments.bkash_secret' => ['nullable', 'string'],
            'settings.security.session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'settings.security.require_password_reset_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], [], [
            'settings.general.organization_name' => 'organization name',
            'settings.general.contact_email' => 'contact email',
            'settings.general.default_locale' => 'default locale',
            'settings.membership.renewal_window_days' => 'renewal window days',
            'settings.membership.max_reversals_allowed_days' => 'max reversals allowed days',
            'settings.membership.default_term_code' => 'default term code',
            'settings.payments.manual_bkash_merchant_number' => 'manual bKash merchant number',
            'settings.payments.default_application_fee_bdt' => 'default application fee',
            'settings.payments.instruction_text' => 'payment instruction text',
            'settings.payments.bkash_secret' => 'bKash merchant secret',
            'settings.security.session_timeout_minutes' => 'session timeout minutes',
            'settings.security.require_password_reset_days' => 'password reset days',
        ]);

        /** @var PlatformSettingsService $service */
        $service = app(PlatformSettingsService::class);

        // 1. General parameters
        $service->set('general.organization_name', $this->settings['general']['organization_name'], $this->workspaceId, 'general', 'string', false, 'Primary organization display name');
        $service->set('general.contact_email', $this->settings['general']['contact_email'], $this->workspaceId, 'general', 'string', false, 'Official primary contact email address');
        $service->set('general.default_locale', $this->settings['general']['default_locale'], $this->workspaceId, 'general', 'string', false, 'Default system locale');

        // 2. Membership parameters
        $service->set('membership.renewal_window_days', (int) $this->settings['membership']['renewal_window_days'], $this->workspaceId, 'membership', 'integer', false, 'Window in days before expiration when renewals are accepted');
        $service->set('membership.max_reversals_allowed_days', (int) $this->settings['membership']['max_reversals_allowed_days'], $this->workspaceId, 'membership', 'integer', false, 'Grace period in days to reverse accidental member conversions');
        $service->set('membership.default_term_code', $this->settings['membership']['default_term_code'], $this->workspaceId, 'membership', 'string', false, 'Default membership term code for new registrations');

        // 3. Payments parameters
        $service->set('payments.manual_bkash_merchant_number', $this->settings['payments']['manual_bkash_merchant_number'], $this->workspaceId, 'payments', 'string', false, 'Manual bKash merchant phone number for offline payments');
        $service->set('payments.default_application_fee_bdt', (string) $this->settings['payments']['default_application_fee_bdt'], $this->workspaceId, 'payments', 'string', false, 'Default application fee in BDT for standard registration');
        $service->set('payments.instruction_text', $this->settings['payments']['instruction_text'] ?? '', $this->workspaceId, 'payments', 'string', false, 'Instruction guide text for payment submissions');

        // Secret credential handling per T-10-08: only update if non-empty and non-masked
        $bkashSecret = $this->settings['payments']['bkash_secret'] ?? null;
        if ($bkashSecret !== '••••••••' && $bkashSecret !== '' && $bkashSecret !== null) {
            $service->set('payments.bkash_secret', $bkashSecret, $this->workspaceId, 'payments', 'encrypted', true, 'bKash merchant API secret credentials');
        }

        // 4. Security parameters
        $service->set('security.session_timeout_minutes', (int) $this->settings['security']['session_timeout_minutes'], $this->workspaceId, 'security', 'integer', false, 'Session inactivity timeout in minutes');
        $service->set('security.require_password_reset_days', (int) $this->settings['security']['require_password_reset_days'], $this->workspaceId, 'security', 'integer', false, 'Interval in days for periodic staff password rotation');

        // Re-mask secret input for security
        $this->settings['payments']['bkash_secret'] = '••••••••';

        session()->flash('status', 'Platform settings updated successfully.');
    }

    public function render(): View
    {
        return view('livewire.platform.platform-settings-hub')
            ->layout('layouts.admin', [
                'title' => 'Platform Settings Hub - EMK Membership CRM',
                'activeModule' => 'platform_services',
            ]);
    }
}
