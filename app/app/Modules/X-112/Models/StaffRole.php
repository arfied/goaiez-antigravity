<?php

declare(strict_types=1);

namespace App\Modules\X112\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class StaffRole extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'staff_roles';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
    ];
}
