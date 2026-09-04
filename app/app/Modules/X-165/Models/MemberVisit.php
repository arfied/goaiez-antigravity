<?php

declare(strict_types=1);

namespace App\Modules\X165\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MemberVisit extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'member_visits';

    protected $guarded = [];

    protected $casts = [
        'used_at' => 'datetime',
        'rolled_over' => 'boolean',
    ];
}
