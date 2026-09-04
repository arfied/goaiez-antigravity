<?php

declare(strict_types=1);

namespace App\Modules\X207\Models;

use Illuminate\Database\Eloquent\Model;

class PushDelivery extends Model
{
    protected $table = 'push_deliveries';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'sanitized' => 'boolean',
    ];
}
