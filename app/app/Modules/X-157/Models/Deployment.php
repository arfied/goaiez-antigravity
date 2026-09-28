<?php

declare(strict_types=1);

namespace App\Modules\X157\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $page_id
 * @property int|null $page_variant_id
 */
class Deployment extends Model
{
    protected $table = 'deployments';

    protected $guarded = [];

    protected $casts = [
        'page_id' => 'integer',
        'page_variant_id' => 'integer',
        'served_count' => 'integer',
        'speed_index' => 'integer',
        'speed_budget_ms' => 'integer',
        'measured_ttfb_ms' => 'integer',
        'deployed_at' => 'datetime',
    ];

    public function edgeZone(): BelongsTo
    {
        return $this->belongsTo(EdgeZone::class, 'edge_zone_id');
    }
}
