<?php

declare(strict_types=1);

namespace App\Modules\CSms\Models;

use Illuminate\Database\Eloquent\Model;

class SmsComposition extends Model
{
    protected $table = 'sms_compositions';

    protected $guarded = [];

    protected $casts = [
        'segments_count' => 'integer',
        'scheduled_at' => 'datetime',
    ];
}
