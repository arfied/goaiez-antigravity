<?php

declare(strict_types=1);

namespace App\Modules\X125\Models;

use Illuminate\Database\Eloquent\Model;

class FlowRun extends Model
{
    protected $table = 'flow_runs';

    protected $guarded = [];

    protected $casts = [
        'trigger_payload' => 'array',
        'is_manual_retry' => 'boolean',
    ];
}
