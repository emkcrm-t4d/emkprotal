<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Platform\Models\PlatformSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PlatformSettingsService
{
    /**
     * Retrieve setting value by key, checking workspace-specific override first,
     * then falling back to global configuration (workspace_id = null).
     */
    public function get(string $key, ?string $workspaceId = null, mixed $default = null): mixed
    {
        if ($workspaceId !== null) {
            $setting = PlatformSetting::query()
                ->where('workspace_id', $workspaceId)
                ->where('key', $key)
                ->first();

            if ($setting !== null) {
                return $setting->value;
            }
        }

        $globalSetting = PlatformSetting::query()
            ->whereNull('workspace_id')
            ->where('key', $key)
            ->first();

        if ($globalSetting !== null) {
            return $globalSetting->value;
        }

        return $default;
    }

    /**
     * Persist or update a platform setting, emitting a sanitized audit log.
     * Sensitive values are scrubbed to '[REDACTED]' in audit trails per DEC-11 & ASVS V8.2.
     */
    public function set(
        string $key,
        mixed $value,
        ?string $workspaceId = null,
        string $group = 'general',
        string $castType = 'string',
        bool $isSecret = false,
        ?string $description = null
    ): PlatformSetting {
        $query = PlatformSetting::query();
        if ($workspaceId !== null) {
            $query->where('workspace_id', $workspaceId);
        } else {
            $query->whereNull('workspace_id');
        }

        /** @var PlatformSetting|null $setting */
        $setting = $query->where('key', $key)->first();

        $isNew = ($setting === null);
        $oldValue = $isNew ? null : $setting->value;
        $wasSecret = $isNew ? false : (bool) $setting->is_secret;
        $oldCastType = $isNew ? 'string' : (string) $setting->cast_type;

        if ($isNew) {
            $setting = new PlatformSetting();
            $setting->workspace_id = $workspaceId;
            $setting->key = $key;
        }

        $setting->group = $group;
        $setting->cast_type = $castType;
        $setting->is_secret = $isSecret;
        $setting->value = $value;

        if ($description !== null) {
            $setting->description = $description;
        }

        $setting->save();

        // Sensitive Token Scrubbing Layer per DEC-11 & ASVS V8.2 (T-10-08)
        $isSensitive = $isSecret || $wasSecret || $castType === 'encrypted' || $oldCastType === 'encrypted';

        $auditOldValue = $isSensitive
            ? ($oldValue !== null ? '[REDACTED]' : null)
            : $oldValue;

        $auditNewValue = $isSensitive
            ? '[REDACTED]'
            : $setting->value;

        $user = Auth::user();

        AuditLog::create([
            'workspace_id' => $workspaceId,
            'actor_id' => $user?->id,
            'actor_guard' => Auth::getDefaultDriver() ?? 'web',
            'event_type' => AuditLog::EVENT_PLATFORM_SETTING_UPDATED,
            'auditable_type' => PlatformSetting::class,
            'auditable_id' => $setting->id,
            'old_values' => [
                'key' => $key,
                'value' => $auditOldValue,
                'group' => $group,
            ],
            'new_values' => [
                'key' => $key,
                'value' => $auditNewValue,
                'group' => $group,
                'cast_type' => $castType,
                'is_secret' => $isSecret,
            ],
            'reason' => "Platform setting [{$key}] updated" . ($workspaceId ? " for workspace [{$workspaceId}]" : ' globally'),
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);

        return $setting;
    }

    /**
     * Retrieve all settings in a group as key => cast value associative array,
     * with workspace-level overrides taking precedence over global settings.
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $group, ?string $workspaceId = null): array
    {
        $result = [];

        // 1. Load global defaults for the group
        $globalSettings = PlatformSetting::query()
            ->whereNull('workspace_id')
            ->where('group', $group)
            ->get();

        foreach ($globalSettings as $setting) {
            $result[$setting->key] = $setting->value;
        }

        // 2. Overlay workspace-specific overrides
        if ($workspaceId !== null) {
            $wsSettings = PlatformSetting::query()
                ->where('workspace_id', $workspaceId)
                ->where('group', $group)
                ->get();

            foreach ($wsSettings as $setting) {
                $result[$setting->key] = $setting->value;
            }
        }

        return $result;
    }

    /**
     * Retrieve all setting models for a group with workspace overrides resolved.
     * Useful for UI hubs needing is_secret, description, and masked_value metadata.
     *
     * @return Collection<string, PlatformSetting>
     */
    public function getGroupModels(string $group, ?string $workspaceId = null): Collection
    {
        $models = new Collection();

        $globalSettings = PlatformSetting::query()
            ->whereNull('workspace_id')
            ->where('group', $group)
            ->get();

        foreach ($globalSettings as $setting) {
            $models->put($setting->key, $setting);
        }

        if ($workspaceId !== null) {
            $wsSettings = PlatformSetting::query()
                ->where('workspace_id', $workspaceId)
                ->where('group', $group)
                ->get();

            foreach ($wsSettings as $setting) {
                $models->put($setting->key, $setting);
            }
        }

        return $models;
    }

    /**
     * Seed baseline operational parameters if not already present.
     */
    public function seedDefaults(): void
    {
        $defaults = [
            [
                'key' => 'general.organization_name',
                'value' => 'EMK Center',
                'group' => 'general',
                'cast_type' => 'string',
                'is_secret' => false,
                'description' => 'Primary organization display name',
            ],
            [
                'key' => 'general.contact_email',
                'value' => 'info@emkcenter.org',
                'group' => 'general',
                'cast_type' => 'string',
                'is_secret' => false,
                'description' => 'Official primary contact email address',
            ],
            [
                'key' => 'membership.default_term_code',
                'value' => 'STANDARD_1YR',
                'group' => 'membership',
                'cast_type' => 'string',
                'is_secret' => false,
                'description' => 'Default membership term code for new registrations',
            ],
            [
                'key' => 'membership.renewal_window_days',
                'value' => 30,
                'group' => 'membership',
                'cast_type' => 'integer',
                'is_secret' => false,
                'description' => 'Window in days before expiration when renewals are accepted',
            ],
            [
                'key' => 'membership.max_reversals_allowed_days',
                'value' => 30,
                'group' => 'membership',
                'cast_type' => 'integer',
                'is_secret' => false,
                'description' => 'Grace period in days to reverse accidental member conversions',
            ],
            [
                'key' => 'payments.manual_bkash_merchant_number',
                'value' => '01700000000',
                'group' => 'payments',
                'cast_type' => 'string',
                'is_secret' => false,
                'description' => 'Manual bKash merchant phone number for offline payments',
            ],
            [
                'key' => 'payments.default_application_fee_bdt',
                'value' => '500.00',
                'group' => 'payments',
                'cast_type' => 'string',
                'is_secret' => false,
                'description' => 'Default application fee in BDT for standard registration',
            ],
            [
                'key' => 'security.session_timeout_minutes',
                'value' => 120,
                'group' => 'security',
                'cast_type' => 'integer',
                'is_secret' => false,
                'description' => 'Session inactivity timeout in minutes',
            ],
            [
                'key' => 'security.require_password_reset_days',
                'value' => 90,
                'group' => 'security',
                'cast_type' => 'integer',
                'is_secret' => false,
                'description' => 'Interval in days for periodic staff password rotation',
            ],
        ];

        foreach ($defaults as $def) {
            $exists = PlatformSetting::query()
                ->whereNull('workspace_id')
                ->where('key', $def['key'])
                ->exists();

            if (! $exists) {
                $this->set(
                    key: $def['key'],
                    value: $def['value'],
                    workspaceId: null,
                    group: $def['group'],
                    castType: $def['cast_type'],
                    isSecret: $def['is_secret'],
                    description: $def['description'],
                );
            }
        }
    }
}
