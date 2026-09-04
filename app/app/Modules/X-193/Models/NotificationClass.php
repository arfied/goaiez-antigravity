<?php

declare(strict_types=1);

namespace App\Modules\X193\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class NotificationClass extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'notification_classes';

    protected $guarded = [];

    protected $casts = [
        'respects_quiet_hours' => 'boolean',
    ];
}
