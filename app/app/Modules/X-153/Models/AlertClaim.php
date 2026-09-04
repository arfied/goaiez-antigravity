<?php

declare(strict_types=1);

namespace App\Modules\X153\Models;

use Illuminate\Database\Eloquent\Model;

class AlertClaim extends Model
{
    protected $table = 'alert_claims';

    protected $guarded = [];

    protected $casts = [
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
