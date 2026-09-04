<?php

declare(strict_types=1);

namespace App\Modules\X108\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'appointments';

    protected $guarded = [];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_member' => 'boolean',
    ];
}
