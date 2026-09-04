<?php

declare(strict_types=1);

namespace App\Modules\X172\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class PortalView extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'portal_views';

    protected $guarded = [];

    protected $casts = [
        'opened_at' => 'datetime',
    ];
}
