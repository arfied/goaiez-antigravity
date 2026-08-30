<?php

declare(strict_types=1);

namespace App\Modules\X123\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $table = 'event_log';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'sequence_number' => 'integer',
        'published_at' => 'datetime',
    ];
}
