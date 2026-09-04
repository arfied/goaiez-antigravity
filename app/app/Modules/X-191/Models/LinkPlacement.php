<?php

declare(strict_types=1);

namespace App\Modules\X191\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class LinkPlacement extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'link_placements';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_monitored_at' => 'datetime',
    ];
}
