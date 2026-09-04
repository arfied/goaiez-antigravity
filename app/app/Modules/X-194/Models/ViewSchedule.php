<?php

declare(strict_types=1);

namespace App\Modules\X194\Models;

use Illuminate\Database\Eloquent\Model;

class ViewSchedule extends Model
{
    protected $table = 'view_schedules';

    protected $guarded = [];

    protected $casts = [
        'recipient_emails' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];
}
