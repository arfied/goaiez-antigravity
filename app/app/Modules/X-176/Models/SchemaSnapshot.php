<?php

declare(strict_types=1);

namespace App\Modules\X176\Models;

use Illuminate\Database\Eloquent\Model;

class SchemaSnapshot extends Model
{
    protected $table = 'schema_snapshots';

    protected $guarded = [];

    protected $casts = [
        'json_ld' => 'array',
        'is_valid_schema' => 'boolean',
    ];
}
