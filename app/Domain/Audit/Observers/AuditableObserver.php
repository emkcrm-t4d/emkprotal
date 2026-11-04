<?php

declare(strict_types=1);

namespace App\Domain\Audit\Observers;

use App\Domain\Audit\Contracts\AuditableInterface;
use App\Domain\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditableObserver
{
    /**
     * Strict list of sensitive tokens that must be redacted from audit payloads per DEC-11 and ASVS V8.2.
     *
     * @var list<string>
     */
    public const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'remember_token',
        'secret',
        'api_key',
        'token',
        'bearer',
        'bkash_pin',
        'private_key',
        'authorization',
        'pin',
        'access_token',
        'refresh_token',
    ];

    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $excluded = $this->resolveExcludedAttributes($model);
        $attributes = $model->attributesToArray();

        foreach ($excluded as $key) {
            unset($attributes[$key]);
        }

        AuditLog::create([
            'workspace_id' => $this->resolveWorkspaceId($model),
            'actor_id' => Auth::id(),
            'actor_guard' => Auth::getDefaultDriver() ?? 'web',
            'event_type' => AuditLog::EVENT_CREATED,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => null,
            'new_values' => $this->redactSensitiveData($attributes),
            'reason' => $this->resolveReason($model),
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $excluded = $this->resolveExcludedAttributes($model);
        $original = $model->getOriginal();
        $attributes = $model->attributesToArray();

        if (property_exists($model, 'auditOnlyChanged') && $model->auditOnlyChanged) {
            $changedKeys = array_keys($model->getChanges());
            $original = array_intersect_key($original, array_flip($changedKeys));
            $attributes = array_intersect_key($attributes, array_flip($changedKeys));
        }

        foreach ($excluded as $key) {
            unset($original[$key], $attributes[$key]);
        }

        AuditLog::create([
            'workspace_id' => $this->resolveWorkspaceId($model),
            'actor_id' => Auth::id(),
            'actor_guard' => Auth::getDefaultDriver() ?? 'web',
            'event_type' => AuditLog::EVENT_UPDATED,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $this->redactSensitiveData($original),
            'new_values' => $this->redactSensitiveData($attributes),
            'reason' => $this->resolveReason($model),
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $excluded = $this->resolveExcludedAttributes($model);
        $original = $model->getOriginal();

        foreach ($excluded as $key) {
            unset($original[$key]);
        }

        AuditLog::create([
            'workspace_id' => $this->resolveWorkspaceId($model),
            'actor_id' => Auth::id(),
            'actor_guard' => Auth::getDefaultDriver() ?? 'web',
            'event_type' => AuditLog::EVENT_DELETED,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $this->redactSensitiveData($original),
            'new_values' => null,
            'reason' => $this->resolveReason($model),
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively redacts sensitive keys in the given attributes array per DEC-11.
     */
    public function redactSensitiveData(array $attributes): array
    {
        $redacted = [];

        foreach ($attributes as $key => $value) {
            $isSensitive = false;
            $lowerKey = strtolower((string) $key);
            $normalizedKey = str_replace(['_', '-'], '', $lowerKey);

            foreach (self::SENSITIVE_KEYS as $sensitiveToken) {
                $lowerToken = strtolower($sensitiveToken);
                $normalizedToken = str_replace(['_', '-'], '', $lowerToken);

                if (str_contains($lowerKey, $lowerToken) || str_contains($normalizedKey, $normalizedToken)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactSensitiveData($value);
            } elseif (is_string($value) && (str_starts_with(trim($value), '{') || str_starts_with(trim($value), '['))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $redacted[$key] = $this->redactSensitiveData($decoded);
                } else {
                    $redacted[$key] = $value;
                }
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Resolve the target workspace identifier for the model mutation.
     */
    protected function resolveWorkspaceId(Model $model): ?string
    {
        if ($model instanceof AuditableInterface) {
            $workspaceId = $model->getAuditWorkspaceId();
            if ($workspaceId !== null) {
                return $workspaceId;
            }
        }

        if (!empty($model->workspace_id)) {
            return (string) $model->workspace_id;
        }

        if (isset($model->lead) && !empty($model->lead->workspace_id)) {
            return (string) $model->lead->workspace_id;
        }

        if (Auth::check() && !empty(Auth::user()->workspace_id)) {
            return (string) Auth::user()->workspace_id;
        }

        return null;
    }

    /**
     * Resolve the optional human audit reason for the model mutation.
     */
    protected function resolveReason(Model $model): ?string
    {
        if ($model instanceof AuditableInterface) {
            $reason = $model->getAuditCustomReason();
            if ($reason !== null) {
                return $reason;
            }
        }

        if (property_exists($model, 'auditCustomReason') && !empty($model->auditCustomReason)) {
            return (string) $model->auditCustomReason;
        }

        if (Request::has('reason')) {
            return (string) Request::input('reason');
        }

        return null;
    }

    /**
     * Resolve excluded attributes for the given model.
     *
     * @return array<int, string>
     */
    protected function resolveExcludedAttributes(Model $model): array
    {
        if ($model instanceof AuditableInterface) {
            return $model->getAuditExcludedAttributes();
        }

        if (property_exists($model, 'auditExcluded') && is_array($model->auditExcluded)) {
            return $model->auditExcluded;
        }

        return [];
    }
}
