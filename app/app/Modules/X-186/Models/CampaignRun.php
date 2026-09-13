<?php

declare(strict_types=1);

namespace App\Modules\X186\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * person_id holds the id of a row in people, never a row in customers. The column does not
 * say which, so this does: StopLog resolves it as a Person, and every writer must pass one
 * (Track 1 ruling, 2026-09-12).
 */
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
