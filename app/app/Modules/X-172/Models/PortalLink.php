<?php

declare(strict_types=1);

namespace App\Modules\X172\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PortalLink extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'portal_links';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];
}
