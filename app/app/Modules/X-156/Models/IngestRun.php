<?php

declare(strict_types=1);

namespace App\Modules\X156\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class IngestRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ingest_runs';

    protected $guarded = [];

    protected $casts = [
        'records_ingested' => 'integer',
    ];
}
