<?php

declare(strict_types=1);

namespace App\Modules\X153\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'alerts';

    protected $guarded = [];

    protected $casts = [
        'claim_expires_at' => 'datetime',
    ];
}
