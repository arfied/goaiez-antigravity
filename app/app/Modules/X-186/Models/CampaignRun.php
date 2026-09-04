<?php

declare(strict_types=1);

namespace App\Modules\X186\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignRun extends Model
{
    protected $table = 'campaign_runs';

    protected $guarded = [];

    protected $casts = [
        'current_step' => 'integer',
        'is_active' => 'boolean',
        'is_suppressed' => 'boolean',
    ];
}
