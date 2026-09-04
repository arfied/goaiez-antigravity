<?php

declare(strict_types=1);

namespace App\Modules\X164\Models;

use Illuminate\Database\Eloquent\Model;

class EstimateLine extends Model
{
    protected $table = 'estimate_lines';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'subtotal_cents' => 'integer',
    ];
}
