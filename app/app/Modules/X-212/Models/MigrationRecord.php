<?php

declare(strict_types=1);

namespace App\Modules\X212\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationRecord extends Model
{
    protected $table = 'migration_records';

    protected $guarded = [];

    protected $casts = [
        'record_index' => 'integer',
        'raw_data' => 'array',
    ];
}
