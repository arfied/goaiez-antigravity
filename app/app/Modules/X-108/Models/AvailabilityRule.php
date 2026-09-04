<?php

declare(strict_types=1);

namespace App\Modules\X108\Models;

use Illuminate\Database\Eloquent\Model;

class AvailabilityRule extends Model
{
    protected $table = 'availability_rules';

    protected $guarded = [];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_blackout' => 'boolean',
    ];
}
