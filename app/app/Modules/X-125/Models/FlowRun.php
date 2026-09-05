<?php

declare(strict_types=1);

namespace App\Modules\X125\Models;

use App\Enums\FlowRunStatus;
use App\Enums\SignalState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property FlowRunStatus $status
 */
class FlowRun extends Model
{
    protected $table = 'flow_runs';

    protected $guarded = [];

    protected $casts = [
        'status' => FlowRunStatus::class,
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

    public function statusSignal(): SignalState
    {
        return match ($this->status) {
            FlowRunStatus::Success => SignalState::Ok,
            FlowRunStatus::Error => SignalState::Alert,
            FlowRunStatus::Simulated => SignalState::Unknown,
        };
    }
}
