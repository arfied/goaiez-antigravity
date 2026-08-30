<?php

declare(strict_types=1);

namespace App\Modules\X173\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingSyncConflict extends Model
{
    protected $table = 'accounting_sync_conflicts';

    protected $guarded = [];

    protected $casts = [
        'confidence_score' => 'float',
        'flagged_for_review' => 'boolean',
    ];
}
