<?php

declare(strict_types=1);

namespace App\Domain\Migration\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoStagingRecord extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'zoho_staging_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'mapped_payload' => 'array',
            'validation_errors' => 'array',
            'imported_entity_id' => 'integer',
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
     * Relationship: The import batch to which this record belongs.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ZohoImportBatch::class, 'batch_id');
    }
}
