<?php

declare(strict_types=1);

namespace App\Modules\X139\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class AdConnection extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'ad_connections';

    protected $guarded = [];

    protected $casts = [
        'is_connected' => 'boolean',
    ];
}
