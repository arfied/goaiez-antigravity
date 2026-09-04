<?php

declare(strict_types=1);

namespace App\Modules\X171\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class DeviceSyncConflict extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'device_sync_conflicts';

    protected $guarded = [];

    protected $casts = [
        'client_version' => 'integer',
        'server_version' => 'integer',
    ];
}
