<?php

declare(strict_types=1);

namespace App\Modules\X111\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class IpBan extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ip_bans';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
