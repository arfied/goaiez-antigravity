<?php

declare(strict_types=1);

namespace App\Modules\X198\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationRun extends Model
{
    protected $table = 'reconciliation_runs';

    protected $guarded = [];

    protected $casts = [
        'expected_cents' => 'integer',
        'actual_cents' => 'integer',
        'discrepancy_cents' => 'integer',
        'reviewed_at' => 'datetime',
    ];
}
