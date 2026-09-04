<?php

declare(strict_types=1);

namespace App\Modules\X136\Models;

use Illuminate\Database\Eloquent\Model;

class DecayModel extends Model
{
    protected $table = 'decay_models';

    protected $guarded = [];

    protected $casts = [
        'half_life_days' => 'integer',
        'decay_rate' => 'float',
    ];
}
