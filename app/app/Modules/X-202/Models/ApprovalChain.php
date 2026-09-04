<?php

declare(strict_types=1);

namespace App\Modules\X202\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalChain extends Model
{
    protected $table = 'approval_chains';

    protected $guarded = [];

    protected $casts = [
        'steps_count' => 'integer',
        'chain_config' => 'array',
    ];
}
