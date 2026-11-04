<?php

declare(strict_types=1);

namespace App\Domain\Leads\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAttachment extends Model
{
    protected $table = 'lead_attachments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'youth_protection' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Relationship: attachment belongs to parent lead.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
