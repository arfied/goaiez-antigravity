<?php

declare(strict_types=1);

namespace App\Modules\X123\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class EventSubscription extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'event_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'failure_count' => 'integer',
        'last_failed_at' => 'datetime',
    ];
}
