<?php

declare(strict_types=1);

namespace App\Modules\X203\Models;

use Illuminate\Database\Eloquent\Model;

class RunbookRun extends Model
{
    public $timestamps = false;

    protected $table = 'runbook_runs';

    protected $guarded = [];

    protected $casts = [
        'executed_steps' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
