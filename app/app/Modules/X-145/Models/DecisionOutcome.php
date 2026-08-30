<?php

declare(strict_types=1);

namespace App\Modules\X145\Models;

use Illuminate\Database\Eloquent\Model;

class DecisionOutcome extends Model
{
    protected $table = 'decision_outcomes';

    protected $guarded = [];

    protected $casts = [
        'is_favorable' => 'boolean',
        'graded_at' => 'datetime',
    ];
}
