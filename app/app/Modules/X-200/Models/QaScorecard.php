<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class QaScorecard extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'qa_scorecards';

    protected $guarded = [];

    protected $casts = [
        'qa_rating' => 'integer',
        'is_positive_only' => 'boolean',
    ];
}
