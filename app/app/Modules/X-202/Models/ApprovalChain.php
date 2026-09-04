<?php

declare(strict_types=1);

namespace App\Modules\X202\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ApprovalChain extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'approval_chains';

    protected $guarded = [];

    protected $casts = [
        'steps_count' => 'integer',
        'chain_config' => 'array',
    ];
}
