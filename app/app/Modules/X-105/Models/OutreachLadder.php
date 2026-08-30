<?php

declare(strict_types=1);

namespace App\Modules\X105\Models;

use Illuminate\Database\Eloquent\Model;

class OutreachLadder extends Model
{
    protected $table = 'outreach_ladders';

    protected $guarded = [];

    protected $casts = [
        'distress_signal_detected' => 'boolean',
        'research_triggered' => 'boolean',
        'exclusive_sms_mode' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(LadderStep::class, 'ladder_id');
    }
}
