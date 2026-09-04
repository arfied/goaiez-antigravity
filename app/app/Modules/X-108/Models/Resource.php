<?php

declare(strict_types=1);

namespace App\Modules\X108\Models;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $table = 'resources';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
