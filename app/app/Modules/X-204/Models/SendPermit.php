<?php

declare(strict_types=1);

namespace App\Modules\X204\Models;

use Illuminate\Database\Eloquent\Model;

class SendPermit extends Model
{
    protected $table = 'send_permits';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
