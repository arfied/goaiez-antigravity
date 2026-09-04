<?php

declare(strict_types=1);

namespace App\Modules\X10\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Territory extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'territories';

    protected $guarded = [];

    protected $casts = [
        'polygon_geojson' => 'array',
        'zip_codes' => 'array',
    ];
}
