<?php

declare(strict_types=1);

namespace App\Domain\Mail\Models;

use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EmailThread extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'email_threads';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (EmailThread $thread): void {
            if (empty($thread->uuid)) {
                $thread->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'message_count' => 'integer',
            'is_resolved' => 'boolean',
        ];
    }

    /**
     * Get the workspace identifier for the tenant entity.
     */
    public function getWorkspaceId(): ?string
    {
        return $this->workspace_id ? (string) $this->workspace_id : null;
    }

    /**
     * Relationship: Messages belonging to this thread ordered chronologically.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class, 'email_thread_id')->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Relationship: Associated Lead applicant record.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: Associated inducted Member record.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
