<?php

declare(strict_types=1);

namespace App\Modules\X200\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CallSchedule extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'call_schedules';

    protected $guarded = [];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'is_completed' => 'boolean',
    ];
}
