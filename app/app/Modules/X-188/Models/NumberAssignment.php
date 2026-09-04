<?php

declare(strict_types=1);

namespace App\Modules\X188\Models;

use Illuminate\Database\Eloquent\Model;

class NumberAssignment extends Model
{
    protected $table = 'number_assignments';

    protected $guarded = [];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];
}
