<?php

declare(strict_types=1);

namespace App\Domain\Workspaces\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $table = 'workspaces';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
