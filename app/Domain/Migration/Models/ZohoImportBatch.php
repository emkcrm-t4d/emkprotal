<?php

declare(strict_types=1);

namespace App\Domain\Migration\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZohoImportBatch extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'zoho_import_batches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'skipped_rows' => 'integer',
            'error_summary' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * Relationship: The staging records processed in this batch.
     */
    public function records(): HasMany
    {
        return $this->hasMany(ZohoStagingRecord::class, 'batch_id');
    }
}
