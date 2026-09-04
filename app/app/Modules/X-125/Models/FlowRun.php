<?php

declare(strict_types=1);

namespace App\Modules\X125\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowRun extends Model
{
    protected $table = 'flow_runs';

    protected $guarded = [];

    protected $casts = [
        'trigger_payload' => 'array',
        'is_manual_retry' => 'boolean',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class, 'flow_id');
    }

    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class, 'flow_version_id');
    }

    public function statusSignal(): \App\Enums\SignalState
    {
        return match ($this->status) {
            'success' => \App\Enums\SignalState::Ok,
            'error' => \App\Enums\SignalState::Alert,
            'simulated' => \App\Enums\SignalState::Unknown,
            default => \App\Enums\SignalState::Unknown,
        };
    }
}
