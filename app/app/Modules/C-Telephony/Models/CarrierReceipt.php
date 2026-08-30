<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierReceipt extends Model
{
    protected $table = 'carrier_receipts';

    protected $guarded = [];

    protected $casts = [
        'cost_cents' => 'integer',
    ];
}
