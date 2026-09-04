<?php

declare(strict_types=1);

namespace App\Modules\X125\Models;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    protected $table = 'flows';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'consecutive_errors' => 'integer',
        'max_error_threshold' => 'integer',
    ];
}
