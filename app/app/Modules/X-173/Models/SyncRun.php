<?php

declare(strict_types=1);

namespace App\Modules\X173\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    protected $table = 'sync_runs';

    protected $guarded = [];

    protected $casts = [
        'records_synced' => 'integer',
        'conflicts_count' => 'integer',
    ];
}
