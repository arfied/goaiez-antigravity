<?php

declare(strict_types=1);

namespace App\Modules\X113\Models;

use Illuminate\Database\Eloquent\Model;

class StaffUser extends Model
{
    protected $table = 'staff_users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];
}
