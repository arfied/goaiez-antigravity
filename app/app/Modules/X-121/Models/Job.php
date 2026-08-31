<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'work_orders';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'price_cents' => 'integer',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
