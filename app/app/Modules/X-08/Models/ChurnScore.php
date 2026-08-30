<?php

declare(strict_types=1);

namespace App\Modules\X08\Models;

use Illuminate\Database\Eloquent\Model;

class ChurnScore extends Model
{
    protected $table = 'churn_scores';

    protected $guarded = [];

    protected $casts = [
        'login_decay_days' => 'integer',
        'roi_open_rate_rising' => 'boolean',
        'risk_score' => 'float',
    ];
}
