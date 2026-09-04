<?php

declare(strict_types=1);

namespace App\Modules\X151\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ProxyPool extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'proxy_pool';

    protected $guarded = [];

    protected $casts = [
        'is_healthy' => 'boolean',
        'rotation_count' => 'integer',
    ];
}
