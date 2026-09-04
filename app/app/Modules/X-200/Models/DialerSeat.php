<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use Illuminate\Database\Eloquent\Model;

class DialerSeat extends Model
{
    protected $table = 'dialer_seats';

    protected $guarded = [];

    protected $casts = [
        'is_ai_agent' => 'boolean',
        'is_logged_in' => 'boolean',
    ];
}
