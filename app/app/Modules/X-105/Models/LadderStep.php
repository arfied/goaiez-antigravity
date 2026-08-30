<?php

declare(strict_types=1);

namespace App\Modules\X105\Models;

use Illuminate\Database\Eloquent\Model;

class LadderStep extends Model
{
    protected $table = 'ladder_steps';

    protected $guarded = [];

    protected $casts = [
        'rung_number' => 'integer',
    ];
}
