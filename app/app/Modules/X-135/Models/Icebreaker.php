<?php

declare(strict_types=1);

namespace App\Modules\X135\Models;

use Illuminate\Database\Eloquent\Model;

class Icebreaker extends Model
{
    protected $table = 'icebreakers';

    protected $guarded = [];

    protected $casts = [
        'observed_date' => 'date',
    ];
}
