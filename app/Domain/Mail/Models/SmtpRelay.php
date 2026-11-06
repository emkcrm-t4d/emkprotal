<?php

declare(strict_types=1);

namespace App\Domain\Mail\Models;

use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmtpRelay extends Model
{
    protected $table = 'smtp_relays';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'port' => 'integer',
            'priority' => 'integer',
            'throttle_seconds' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderedByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc')->orderBy('id', 'asc');
    }

    public function scopeForWorkspace(Builder $query, ?string $workspaceId, bool $includeGlobal = true): Builder
    {
        return $query->where(function (Builder $q) use ($workspaceId, $includeGlobal) {
            if (!empty($workspaceId)) {
                $q->where('workspace_id', $workspaceId);
            }
            if ($includeGlobal) {
                $q->orWhereNull('workspace_id');
            }
        });
    }

    public function isBroken(): bool
    {
        return $this->status === 'error';
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Get a human-readable, clean diagnostic summary of the last error.
     */
    public function getFriendlyErrorAttribute(): ?string
    {
        if (empty($this->last_error_message)) {
            return null;
        }

        $raw = (string) $this->last_error_message;

        if (str_contains($raw, '535') || str_contains($raw, 'Authentication failed')) {
            return "Authentication Rejected (Error 535): Invalid Brevo API Master Key or SMTP username for '{$this->username}'. Please verify your credentials.";
        }

        if (str_contains($raw, 'timed out') || str_contains($raw, 'Connection timed out')) {
            return "Connection Timeout: Host '{$this->host}' did not respond on port {$this->port} within the timeout window.";
        }

        if (str_contains($raw, 'Connection refused')) {
            return "Connection Refused: Target server '{$this->host}:{$this->port}' rejected the TCP handshake. Ensure the port is open.";
        }

        if (str_contains($raw, 'Certificate') || str_contains($raw, 'SSL') || str_contains($raw, 'TLS')) {
            return "TLS/SSL Security Negotiation Failed: Check encryption protocol ('{$this->encryption}') and certificates.";
        }

        if (str_contains($raw, 'getaddrinfo') || str_contains($raw, 'Name or service not known')) {
            return "DNS Lookup Failed: Hostname '{$this->host}' could not be resolved.";
        }

        return \Illuminate\Support\Str::limit($raw, 160);
    }
}

