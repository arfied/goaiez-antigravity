<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use Illuminate\Database\Eloquent\Model;

class QaScorecard extends Model
{
    protected $table = 'qa_scorecards';

    protected $guarded = [];

    protected $casts = [
        'score' => 'integer',
        'is_positive_only' => 'boolean',
    ];
}
