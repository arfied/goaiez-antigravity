<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use Illuminate\Database\Eloquent\Model;

class CallCampaign extends Model
{
    protected $table = 'call_campaigns';

    protected $guarded = [];

    protected $casts = [
        'abandonment_ceiling_pct' => 'float',
        'is_running' => 'boolean',
    ];
}
