<?php

declare(strict_types=1);

namespace App\Modules\X151\Models;

use Illuminate\Database\Eloquent\Model;

class ProxyPool extends Model
{
    protected $table = 'proxy_pool';

    protected $guarded = [];

    protected $casts = [
        'is_healthy' => 'boolean',
        'rotation_count' => 'integer',
    ];
}
