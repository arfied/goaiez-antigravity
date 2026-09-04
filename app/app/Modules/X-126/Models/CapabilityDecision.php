<?php

declare(strict_types=1);

namespace App\Modules\X126\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CapabilityDecision extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'capability_decisions';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
    ];
}
