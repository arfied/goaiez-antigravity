<?php

declare(strict_types=1);

namespace App\Modules\X170\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'commission_rules';

    protected $guarded = [];

    protected $casts = [
        'percentage' => 'float',
        'threshold_cents' => 'integer',
        'is_active' => 'boolean',
    ];
}
