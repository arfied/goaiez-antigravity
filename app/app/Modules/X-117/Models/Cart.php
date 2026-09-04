<?php

declare(strict_types=1);

namespace App\Modules\X117\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'carts';

    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'total_cents' => 'integer',
        'expires_at' => 'datetime',
    ];
}
