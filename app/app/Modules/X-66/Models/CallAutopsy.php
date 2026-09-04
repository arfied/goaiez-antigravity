<?php

declare(strict_types=1);

namespace App\Modules\X66\Models;

use Illuminate\Database\Eloquent\Model;

class CallAutopsy extends Model
{
    protected $table = 'call_autopsies';

    protected $guarded = [];

    protected $casts = [
        'metrics' => 'array',
    ];
}
