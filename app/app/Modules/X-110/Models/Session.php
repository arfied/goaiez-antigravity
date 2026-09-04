<?php

declare(strict_types=1);

namespace App\Modules\X110\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Session extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'visitor_sessions';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
