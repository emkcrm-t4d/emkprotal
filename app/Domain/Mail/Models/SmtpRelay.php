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

    protected $guarded = [];

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
}

