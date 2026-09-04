<?php

declare(strict_types=1);

namespace App\Modules\X162\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchAssignment extends Model
{
    protected $table = 'dispatch_assignments';

    protected $guarded = [];

    protected $casts = [
        'en_route_at' => 'datetime',
    ];
}
