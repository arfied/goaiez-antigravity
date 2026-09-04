<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CallCampaign extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'call_campaigns';

    protected $guarded = [];

    protected $casts = [
        'abandonment_ceiling_pct' => 'float',
        'is_running' => 'boolean',
    ];
}
