<?php

declare(strict_types=1);

namespace App\Modules\X150\Models;

use Illuminate\Database\Eloquent\Model;

class RecordShape extends Model
{
    protected $table = 'record_shapes';

    protected $guarded = [];

    protected $casts = [
        'schema_definition' => 'array',
    ];
}
