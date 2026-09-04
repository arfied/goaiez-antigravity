<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use Illuminate\Database\Eloquent\Model;

class Number extends Model
{
    protected $table = 'numbers';

    protected $guarded = [];

    protected $casts = [
        'provisioned_at' => 'datetime',
    ];
}
