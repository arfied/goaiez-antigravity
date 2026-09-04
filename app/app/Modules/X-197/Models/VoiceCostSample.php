<?php

declare(strict_types=1);

namespace App\Modules\X197\Models;

use Illuminate\Database\Eloquent\Model;

class VoiceCostSample extends Model
{
    protected $table = 'voice_cost_samples';

    protected $guarded = [];

    protected $casts = [
        'cost_per_minute' => 'integer',
        'sample_timestamp' => 'datetime',
    ];
}
