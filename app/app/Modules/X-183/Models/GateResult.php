<?php

declare(strict_types=1);

namespace App\Modules\X183\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class GateResult extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'gate_results';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'checked_at' => 'datetime',
    ];
}
