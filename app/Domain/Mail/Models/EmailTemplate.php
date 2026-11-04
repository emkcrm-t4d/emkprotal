<?php

declare(strict_types=1);

namespace App\Domain\Mail\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    public const MILESTONE_APPLICATION_RECEIPT = 'application_receipt';
    public const MILESTONE_MISSING_ITEMS = 'missing_items';
    public const MILESTONE_REJECTION = 'application_rejection';
    public const MILESTONE_CONVERSION_CONFIRMATION = 'conversion_confirmation';
    public const MILESTONE_PORTAL_ACTIVATION = 'portal_activation';
    public const MILESTONE_ID_CARD_READY = 'id_card_ready';
    public const MILESTONE_RENEWAL_WINDOW = 'renewal_window';
    public const MILESTONE_EXPIRY = 'membership_expiry';
    public const MILESTONE_REQUEST_OUTCOME = 'request_outcome';

    public const CANONICAL_MILESTONES = [
        self::MILESTONE_APPLICATION_RECEIPT,
        self::MILESTONE_MISSING_ITEMS,
        self::MILESTONE_REJECTION,
        self::MILESTONE_CONVERSION_CONFIRMATION,
        self::MILESTONE_PORTAL_ACTIVATION,
        self::MILESTONE_ID_CARD_READY,
        self::MILESTONE_RENEWAL_WINDOW,
        self::MILESTONE_EXPIRY,
        self::MILESTONE_REQUEST_OUTCOME,
    ];

    protected $table = 'email_templates';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allowed_variables' => 'array',
            'is_active' => 'boolean',
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
     * Relationship: Staff member who last updated the template.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
