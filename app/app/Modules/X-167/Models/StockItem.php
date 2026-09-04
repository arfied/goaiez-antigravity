<?php

declare(strict_types=1);

namespace App\Modules\X167\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'stock_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'float',
        'reorder_point' => 'float',
    ];
}
