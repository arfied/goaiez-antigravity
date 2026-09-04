<?php

declare(strict_types=1);

namespace App\Modules\X117\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Order extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'orders';

    protected $guarded = [];

    protected $casts = [
        'total_cents' => 'integer',
    ];
}
