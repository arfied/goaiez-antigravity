<?php

declare(strict_types=1);

namespace App\Modules\X162\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DispatchAssignment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'dispatch_assignments';

    protected $guarded = [];

    protected $casts = [
        'en_route_at' => 'datetime',
    ];
}
