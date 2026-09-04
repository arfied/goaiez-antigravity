<?php

declare(strict_types=1);

namespace App\Modules\X209\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class StaffCalendarLink extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'staff_calendar_links';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
