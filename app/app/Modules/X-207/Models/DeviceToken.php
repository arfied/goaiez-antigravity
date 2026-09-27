<?php

declare(strict_types=1);

namespace App\Modules\X207\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    protected $table = 'device_tokens';

    protected $guarded = [];

    protected $casts = [
        'subscription' => 'array',
    ];
}
