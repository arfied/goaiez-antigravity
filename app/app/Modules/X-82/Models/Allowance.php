<?php

declare(strict_types=1);

namespace App\Modules\X82\Models;

use Illuminate\Database\Eloquent\Model;

class Allowance extends Model
{
    protected $table = 'allowances';

    protected $guarded = [];

    protected $casts = [
        'units_granted' => 'integer',
        'units_used' => 'integer',
        'expires_at' => 'datetime',
    ];
}
