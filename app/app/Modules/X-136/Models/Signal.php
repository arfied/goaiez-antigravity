<?php

declare(strict_types=1);

namespace App\Modules\X136\Models;

use Illuminate\Database\Eloquent\Model;

class Signal extends Model
{
    protected $table = 'signals';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
