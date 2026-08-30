<?php

declare(strict_types=1);

namespace App\Modules\X185\Models;

use Illuminate\Database\Eloquent\Model;

class SequenceStep extends Model
{
    protected $table = 'sequence_steps';

    protected $guarded = [];

    protected $casts = [
        'step_number' => 'integer',
        'delay_hours' => 'integer',
    ];
}
