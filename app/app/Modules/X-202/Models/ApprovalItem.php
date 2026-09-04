<?php

declare(strict_types=1);

namespace App\Modules\X202\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ApprovalItem extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'approval_items';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'is_l1_forever' => 'boolean',
        'current_step' => 'integer',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
