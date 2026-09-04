<?php

declare(strict_types=1);

namespace App\Modules\X117\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Sellable extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'sellables';

    protected $guarded = [];

    protected $casts = [
        'inventory_quantity' => 'integer',
        'unit_price_cents' => 'integer',
    ];
}
