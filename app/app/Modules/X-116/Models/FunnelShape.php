<?php

declare(strict_types=1);

namespace App\Modules\X116\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class FunnelShape extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'funnel_shapes';

    protected $guarded = [];

    protected $casts = [
        'steps_flow' => 'array',
    ];
}
