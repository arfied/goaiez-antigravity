<?php

declare(strict_types=1);

namespace App\Modules\X183\Models;

use Illuminate\Database\Eloquent\Model;

class GateResult extends Model
{
    protected $table = 'gate_results';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'checked_at' => 'datetime',
    ];
}
