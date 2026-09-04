<?php

declare(strict_types=1);

namespace App\Modules\X165\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'memberships';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'renews_at' => 'datetime',
        'renewal_reminder_sent_at' => 'datetime',
    ];
}
