<?php

declare(strict_types=1);

namespace App\Domain\Members\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberChangeRequest extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    public const TYPE_GENERAL_UPDATE = 'general_update';
    public const TYPE_EMAIL_CHANGE = 'email_change';
    public const TYPE_RENEWAL = 'renewal';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'member_change_requests';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_payload' => 'array',
            'new_payload' => 'array',
            'reviewed_at' => 'datetime',
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
     * Relationship: The member who requested this change.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Relationship: Staff user who reviewed and decided this change request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
