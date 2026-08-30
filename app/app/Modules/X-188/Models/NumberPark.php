<?php

declare(strict_types=1);

namespace App\Modules\X188\Models;

use Illuminate\Database\Eloquent\Model;

class NumberPark extends Model
{
    protected $table = 'number_parks';

    protected $guarded = [];

    protected $casts = [
        'parked_at' => 'datetime',
        'park_until' => 'datetime',
        'is_released' => 'boolean',
    ];
}
