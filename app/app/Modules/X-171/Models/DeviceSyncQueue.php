<?php

declare(strict_types=1);

namespace App\Modules\X171\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceSyncQueue extends Model
{
    protected $table = 'device_sync_queue';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'version' => 'integer',
    ];
}
