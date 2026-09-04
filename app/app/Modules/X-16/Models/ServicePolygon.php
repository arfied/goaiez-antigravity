<?php

declare(strict_types=1);

namespace App\Modules\X16\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ServicePolygon extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'service_polygons';

    protected $guarded = [];

    protected $casts = [
        'coordinates' => 'array',
        'is_active' => 'boolean',
    ];
}
