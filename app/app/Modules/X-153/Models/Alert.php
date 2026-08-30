<?php

declare(strict_types=1);

namespace App\Modules\X153\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $table = 'alerts';

    protected $guarded = [];

    protected $casts = [
        'claim_expires_at' => 'datetime',
    ];
}
