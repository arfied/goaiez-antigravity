<?php

declare(strict_types=1);

namespace App\Modules\X188\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class NumberAssignment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'number_assignments';

    protected $guarded = [];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];
}
