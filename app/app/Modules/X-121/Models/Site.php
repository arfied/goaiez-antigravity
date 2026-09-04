<?php

declare(strict_types=1);

namespace App\Modules\X121\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Site extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'sites';

    protected $guarded = [];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'last_crawled_at' => 'datetime',
    ];
}
