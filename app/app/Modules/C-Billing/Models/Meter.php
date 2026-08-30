<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Models;

use Illuminate\Database\Eloquent\Model;

class Meter extends Model
{
    protected $table = 'meters';

    protected $guarded = [];

    protected $casts = [
        'units_used' => 'integer',
        'cost_hundredths_cents' => 'integer',
    ];
}
