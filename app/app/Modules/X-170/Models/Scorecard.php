<?php

declare(strict_types=1);

namespace App\Modules\X170\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Scorecard extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'scorecards';

    protected $guarded = [];

    protected $casts = [
        'revenue_collected_cents' => 'integer',
        'commissions_earned_cents' => 'integer',
        'average_review_score' => 'float',
    ];
}
