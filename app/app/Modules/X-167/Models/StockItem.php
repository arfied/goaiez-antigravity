<?php

declare(strict_types=1);

namespace App\Modules\X167\Models;

use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    protected $table = 'stock_items';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'float',
        'reorder_point' => 'float',
    ];
}
