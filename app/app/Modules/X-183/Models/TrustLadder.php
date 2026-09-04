<?php

declare(strict_types=1);

namespace App\Modules\X183\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class TrustLadder extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'trust_ladder';

    protected $guarded = [];

    protected $casts = [
        'consecutive_approved_count' => 'integer',
        'unattended' => 'boolean',
    ];
}
