<?php

declare(strict_types=1);

namespace App\Modules\X166\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MarginSnapshot extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'margin_snapshots';

    protected $guarded = [];

    protected $casts = [
        'total_revenue_cents' => 'integer',
        'total_cost_cents' => 'integer',
        'gross_margin_cents' => 'integer',
        'margin_pct' => 'float',
    ];
}
