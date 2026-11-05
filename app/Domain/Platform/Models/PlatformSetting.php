<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class PlatformSetting extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'platform_settings';

    protected $fillable = [
        'key',
        'workspace_id',
        'group',
        'value',
        'cast_type',
        'is_secret',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    /**
     * Override BelongsToTenant boot logic to permit global (workspace_id = null)
     * fallback parameters without requiring architectural bypasses (withoutGlobalScopes).
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('platform_setting_tenant', function (Builder $builder): void {
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();

            if (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin'])) {
                return;
            }

            if (!empty($user->workspace_id)) {
                $builder->where(function (Builder $query) use ($user): void {
                    $query->where('workspace_id', (string) $user->workspace_id)
                        ->orWhereNull('workspace_id');
                });
            } else {
                $builder->whereNull('workspace_id');
            }
        });
    }

    /**
     * Get the workspace identifier for the tenant entity.
     */
    public function getWorkspaceId(): ?string
    {
        return $this->workspace_id ? (string) $this->workspace_id : null;
    }

    /**
     * Relationship: The workspace to which this setting override belongs.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'id');
    }

    /**
     * Getter mutator: automatically convert stored raw value to typed value based on cast_type.
     */
    public function getValueAttribute(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        $castType = $this->cast_type ?? 'string';

        return match ($castType) {
            'encrypted' => Crypt::decryptString($raw),
            'json' => json_decode($raw, true),
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $raw,
            default => $raw,
        };
    }

    /**
     * Setter mutator: automatically format / encrypt value before persistence.
     */
    public function setValueAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['value'] = null;
            return;
        }

        $castType = $this->attributes['cast_type'] ?? $this->cast_type ?? 'string';

        switch ($castType) {
            case 'encrypted':
                $this->attributes['value'] = Crypt::encryptString((string) $value);
                break;
            case 'json':
                $this->attributes['value'] = is_string($value)
                    ? $value
                    : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                break;
            case 'boolean':
                $this->attributes['value'] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                break;
            case 'integer':
                $this->attributes['value'] = (string) ((int) $value);
                break;
            default:
                $this->attributes['value'] = (string) $value;
                break;
        }
    }

    /**
     * Handle cast_type changes when value was assigned first.
     */
    public function setCastTypeAttribute(string $type): void
    {
        $this->attributes['cast_type'] = $type;

        if ($type === 'encrypted' && isset($this->attributes['value']) && $this->attributes['value'] !== null) {
            try {
                Crypt::decryptString($this->attributes['value']);
            } catch (\Throwable) {
                $this->attributes['value'] = Crypt::encryptString($this->attributes['value']);
            }
        }
    }

    /**
     * Accessor: masked representation for presentation views per T-10-08 and DASH-04.
     */
    public function getMaskedValueAttribute(): string
    {
        if ($this->is_secret) {
            return '••••••••';
        }

        $val = $this->value;

        if ($val === null) {
            return '';
        }

        if (is_array($val)) {
            return (string) json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }

        return (string) $val;
    }
}
