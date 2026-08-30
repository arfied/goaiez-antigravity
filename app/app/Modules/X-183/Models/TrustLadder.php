<?php

declare(strict_types=1);

namespace App\Modules\X183\Models;

use Illuminate\Database\Eloquent\Model;

class TrustLadder extends Model
{
    protected $table = 'trust_ladder';

    protected $guarded = [];

    protected $casts = [
        'consecutive_approved_count' => 'integer',
        'unattended' => 'boolean',
    ];
}
