<?php

declare(strict_types=1);

namespace App\Modules\X129\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class RedirectMap extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'redirect_maps';

    protected $guarded = [];

    protected $casts = [
        'status_code' => 'integer',
        'is_verified' => 'boolean',
    ];
}
