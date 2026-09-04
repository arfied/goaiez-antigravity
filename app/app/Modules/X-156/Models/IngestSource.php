<?php

declare(strict_types=1);

namespace App\Modules\X156\Models;

use Illuminate\Database\Eloquent\Model;

class IngestSource extends Model
{
    protected $table = 'ingest_sources';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
