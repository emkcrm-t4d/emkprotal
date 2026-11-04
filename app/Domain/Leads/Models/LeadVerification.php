<?php

declare(strict_types=1);

namespace App\Domain\Leads\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadVerification extends Model
{
    protected $table = 'lead_verifications';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence_payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Relationship: verification belongs to parent lead.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: staff user who performed verification.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * Relationship: reviewer user who performed verification.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
