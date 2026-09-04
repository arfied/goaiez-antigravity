<?php

declare(strict_types=1);

namespace App\Modules\X193\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationClass extends Model
{
    protected $table = 'notification_classes';

    protected $guarded = [];

    protected $casts = [
        'respects_quiet_hours' => 'boolean',
    ];
}
