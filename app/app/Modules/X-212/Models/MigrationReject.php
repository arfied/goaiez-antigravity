<?php

declare(strict_types=1);

namespace App\Modules\X212\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationReject extends Model
{
    protected $table = 'migration_rejects';

    protected $guarded = [];

    protected $casts = [
        'record_index' => 'integer',
        'raw_data' => 'array',
        'resolved_at' => 'datetime',
    ];
}
