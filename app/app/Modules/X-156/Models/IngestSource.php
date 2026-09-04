<?php

declare(strict_types=1);

namespace App\Modules\X156\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class IngestSource extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ingest_sources';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
