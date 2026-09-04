<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Meter extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'meters';

    protected $guarded = [];

    protected $casts = [
        'units_used' => 'integer',
        'cost_hundredths_cents' => 'integer',
    ];
}
