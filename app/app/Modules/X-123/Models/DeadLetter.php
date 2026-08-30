<?php

declare(strict_types=1);

namespace App\Modules\X123\Models;

use Illuminate\Database\Eloquent\Model;

class DeadLetter extends Model
{
    protected $table = 'dead_letters';

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'notified_at' => 'datetime',
        'replayed_at' => 'datetime',
    ];
}
