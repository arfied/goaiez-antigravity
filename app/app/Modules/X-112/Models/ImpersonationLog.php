<?php

declare(strict_types=1);

namespace App\Modules\X112\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ImpersonationLog extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'impersonation_log';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
