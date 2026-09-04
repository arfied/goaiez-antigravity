<?php

declare(strict_types=1);

namespace App\Modules\X111\Models;

use Illuminate\Database\Eloquent\Model;

class IpBan extends Model
{
    protected $table = 'ip_bans';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
