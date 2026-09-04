<?php

declare(strict_types=1);

namespace App\Modules\X201\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeOutcome extends Model
{
    protected $table = 'dispute_outcomes';

    protected $guarded = [];

    protected $casts = [
        'commission_clawback_triggered' => 'boolean',
    ];
}
