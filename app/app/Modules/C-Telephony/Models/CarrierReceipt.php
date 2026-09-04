<?php

declare(strict_types=1);

namespace App\Modules\CTelephony\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CarrierReceipt extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'carrier_receipts';

    protected $guarded = [];

    protected $casts = [
        'cost_cents' => 'integer',
    ];
}
