<?php

declare(strict_types=1);

namespace App\Modules\X108\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'resources';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
