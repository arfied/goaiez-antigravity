<?php

declare(strict_types=1);

namespace App\Modules\X172\Models;

use Illuminate\Database\Eloquent\Model;

class PortalLink extends Model
{
    protected $table = 'portal_links';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];
}
