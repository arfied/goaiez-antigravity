<?php

declare(strict_types=1);

namespace App\Modules\X156\Models;

use Illuminate\Database\Eloquent\Model;

class IngestRun extends Model
{
    protected $table = 'ingest_runs';

    protected $guarded = [];

    protected $casts = [
        'records_ingested' => 'integer',
    ];
}
