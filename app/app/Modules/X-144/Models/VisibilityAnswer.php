<?php

declare(strict_types=1);

namespace App\Modules\X144\Models;

use Illuminate\Database\Eloquent\Model;

class VisibilityAnswer extends Model
{
    protected $table = 'visibility_answers';

    protected $guarded = [];

    protected $casts = [
        'asked_at' => 'date',
        'tenant_mentioned' => 'boolean',
        'competitor_outranking' => 'boolean',
        'rank_position' => 'integer',
    ];
}
