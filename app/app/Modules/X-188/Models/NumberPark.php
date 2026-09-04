<?php

declare(strict_types=1);

namespace App\Modules\X188\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class NumberPark extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'number_parks';

    protected $guarded = [];

    protected $casts = [
        'parked_at' => 'datetime',
        'park_until' => 'datetime',
        'is_released' => 'boolean',
    ];
}
