<?php

declare(strict_types=1);

namespace App\Modules\X194\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ViewSchedule extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'view_schedules';

    protected $guarded = [];

    protected $casts = [
        'recipient_emails' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];
}
