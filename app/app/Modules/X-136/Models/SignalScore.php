<?php

declare(strict_types=1);

namespace App\Modules\X136\Models;

use Illuminate\Database\Eloquent\Model;

class SignalScore extends Model
{
    protected $table = 'signal_scores';

    protected $guarded = [];

    protected $casts = [
        'signal_value' => 'float',
        'is_high_intent' => 'boolean',
    ];
}
