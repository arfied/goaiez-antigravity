<?php

declare(strict_types=1);

namespace App\Modules\X147\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class RcsCapability extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'rcs_capabilities';

    protected $guarded = [];

    protected $casts = [
        'has_rcs' => 'boolean',
        'degraded_count' => 'integer',
        'last_checked_at' => 'datetime',
    ];

    public function logDegrade(): void
    {
        $this->increment('degraded_count');
    }
}
