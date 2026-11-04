<?php

declare(strict_types=1);

namespace App\Domain\Mail\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRule extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    public const TRIGGER_LEAD_SUBMITTED = 'lead.submitted';
    public const TRIGGER_LEAD_MISSING_ITEMS = 'lead.missing_items';
    public const TRIGGER_LEAD_REJECTED = 'lead.rejected';
    public const TRIGGER_LEAD_CONVERTED = 'lead.converted';
    public const TRIGGER_PORTAL_ACTIVATED = 'member.portal_activated';
    public const TRIGGER_ID_CARD_READY = 'id_card.ready_for_pickup';
    public const TRIGGER_RENEWAL_WINDOW_OPEN = 'member.renewal_window_open';
    public const TRIGGER_MEMBER_EXPIRED = 'member.expired';
    public const TRIGGER_REQUEST_OUTCOME = 'member.change_request_outcome';

    public const ALLOWED_TRIGGERS = [
        self::TRIGGER_LEAD_SUBMITTED,
        self::TRIGGER_LEAD_MISSING_ITEMS,
        self::TRIGGER_LEAD_REJECTED,
        self::TRIGGER_LEAD_CONVERTED,
        self::TRIGGER_PORTAL_ACTIVATED,
        self::TRIGGER_ID_CARD_READY,
        self::TRIGGER_RENEWAL_WINDOW_OPEN,
        self::TRIGGER_MEMBER_EXPIRED,
        self::TRIGGER_REQUEST_OUTCOME,
    ];

    protected $table = 'workflow_rules';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'days_offset' => 'integer',
            'conditions' => 'array',
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
     * Relationship: The email template bound to this workflow rule.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /**
     * Relationship: Staff member who last updated the workflow rule.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
