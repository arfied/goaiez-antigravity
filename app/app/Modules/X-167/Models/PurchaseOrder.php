<?php

declare(strict_types=1);

namespace App\Modules\X167\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'total_cents' => 'integer',
    ];
}
