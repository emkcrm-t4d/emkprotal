<?php

declare(strict_types=1);

namespace App\Domain\Leads\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $table = 'leads';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'has_parental_consent' => 'boolean',
            'has_us_experience' => 'boolean',
            'is_american_alumni' => 'boolean',
            'terms_agreed' => 'boolean',
            'submitted_at' => 'datetime',
            'age_at_submission' => 'integer',
        ];
    }

    /**
     * Relationship: lead has many attachments (identity scan, photo, parental consent).
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class, 'lead_id');
    }
}
