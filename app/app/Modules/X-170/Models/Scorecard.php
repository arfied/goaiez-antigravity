<?php

declare(strict_types=1);

namespace App\Modules\X170\Models;

use Illuminate\Database\Eloquent\Model;

class Scorecard extends Model
{
    protected $table = 'scorecards';

    protected $guarded = [];

    protected $casts = [
        'revenue_collected_cents' => 'integer',
        'commissions_earned_cents' => 'integer',
        'average_review_score' => 'float',
    ];
}
