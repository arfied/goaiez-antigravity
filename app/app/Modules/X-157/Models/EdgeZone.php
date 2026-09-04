<?php

declare(strict_types=1);

namespace App\Modules\X157\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class EdgeZone extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'edge_zones';

    protected $guarded = [];

    protected $casts = [
        'has_valid_ssl' => 'boolean',
    ];
}
