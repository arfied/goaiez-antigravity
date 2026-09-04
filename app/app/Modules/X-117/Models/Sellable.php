<?php

declare(strict_types=1);

namespace App\Modules\X117\Models;

use Illuminate\Database\Eloquent\Model;

class Sellable extends Model
{
    protected $table = 'sellables';

    protected $guarded = [];

    protected $casts = [
        'inventory_quantity' => 'integer',
        'unit_price_cents' => 'integer',
    ];
}
