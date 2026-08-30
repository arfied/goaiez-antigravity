<?php

declare(strict_types=1);

namespace App\Modules\X209\Models;

use Illuminate\Database\Eloquent\Model;

class StaffCalendarLink extends Model
{
    protected $table = 'staff_calendar_links';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
