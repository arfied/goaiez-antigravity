<?php

declare(strict_types=1);

namespace App\Modules\X186\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CampaignStep extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'campaign_steps';

    protected $guarded = [];

    protected $casts = [
        'step_number' => 'integer',
        'delay_days' => 'integer',
    ];
}
