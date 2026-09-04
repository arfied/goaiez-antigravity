<?php

declare(strict_types=1);

namespace App\Modules\X204\Models;

use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    protected $table = 'suppressions';

    protected $guarded = [];

    protected $casts = [
        'suppressed_at' => 'datetime',
    ];
}
