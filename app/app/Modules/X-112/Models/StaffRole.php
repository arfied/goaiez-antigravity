<?php

declare(strict_types=1);

namespace App\Modules\X112\Models;

use Illuminate\Database\Eloquent\Model;

class StaffRole extends Model
{
    protected $table = 'staff_roles';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];
}
