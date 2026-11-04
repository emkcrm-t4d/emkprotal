<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const EVENT_CREATED = 'entity.created';
    public const EVENT_UPDATED = 'entity.updated';
    public const EVENT_DELETED = 'entity.deleted';
    public const EVENT_LOGIN_SUCCESS = 'auth.login_success';
    public const EVENT_LOGIN_FAILED = 'auth.login_failed';
    public const EVENT_LOGOUT = 'auth.logout';
    public const EVENT_PASSWORD_RESET = 'auth.password_reset';
    public const EVENT_PERMISSION_DELEGATED = 'access.permission_delegated';
    public const EVENT_PERMISSION_REVOKED = 'access.permission_revoked';
    public const EVENT_DOCUMENT_ACCESSED = 'document.accessed';
    public const EVENT_FORM_SUBMISSIONS_EXPORTED = 'form_submissions_exported';
    public const EVENT_LEAD_MISSING_ITEMS_SENT = 'lead_missing_items_sent';
    public const EVENT_LEAD_REJECTED = 'lead_rejected';
    public const EVENT_LEAD_BLOCKED = 'lead_blocked';
    public const EVENT_CONVERSION_COMPLETED = 'conversion_completed';
    public const EVENT_CONVERSION_REVERSED = 'conversion_reversed';
    public const EVENT_MEMBER_CONTACT_UPDATED = 'member_contact_updated';
    public const EVENT_MEMBERSHIP_TERM_CREATED = 'membership_term_created';
    public const EVENT_MEMBERSHIP_TERM_UPDATED = 'membership_term_updated';
    public const EVENT_MEMBERSHIP_RENEWED = 'membership_renewed';
    public const EVENT_INVOICE_VOIDED = 'invoice_voided';
    public const EVENT_INVOICES_EXPORTED = 'invoices_exported';
    public const EVENT_ID_CARD_PRINTED = 'id_card_printed';
    public const EVENT_ID_CARD_DELIVERED = 'id_card_delivered';
    public const EVENT_ID_CARDS_BATCH_EXPORTED = 'id_cards_batch_exported';

    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'actor_id',
        'actor_guard',
        'event_type',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
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
     * Relationship: The user who performed the logged action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Relationship: The workspace to which the audit record belongs.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'id');
    }

    /**
     * Polymorphic relationship: The target entity of the audited action.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
