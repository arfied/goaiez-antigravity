<?php

declare(strict_types=1);

namespace App\Modules\X141\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ReplayRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'replay_runs';

    protected $guarded = [];

    protected $casts = [
        'events_replayed' => 'integer',
        'divergences_found' => 'integer',
        'runtime_cost_cents' => 'integer',
    ];
}
