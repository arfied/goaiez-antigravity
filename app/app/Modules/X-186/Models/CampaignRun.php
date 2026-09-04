<?php

declare(strict_types=1);

namespace App\Modules\X186\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CampaignRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'campaign_runs';

    protected $guarded = [];

    protected $casts = [
        'current_step' => 'integer',
        'is_active' => 'boolean',
        'is_suppressed' => 'boolean',
    ];
}
