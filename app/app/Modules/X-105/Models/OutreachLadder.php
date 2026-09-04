<?php

declare(strict_types=1);

namespace App\Modules\X105\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachLadder extends Model
{
    protected $table = 'outreach_ladders';

    protected $guarded = [];

    protected $casts = [
        'distress_signal_detected' => 'boolean',
        'research_triggered' => 'boolean',
        'exclusive_sms_mode' => 'boolean',
    ];

    /**
     * @return HasMany<LadderStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(LadderStep::class, 'ladder_id');
    }
}
