<?php

declare(strict_types=1);

namespace App\Modules\X141\Models;

use Illuminate\Database\Eloquent\Model;

class ReplayRun extends Model
{
    protected $table = 'replay_runs';

    protected $guarded = [];

    protected $casts = [
        'events_replayed' => 'integer',
        'divergences_found' => 'integer',
        'runtime_cost_cents' => 'integer',
    ];
}
