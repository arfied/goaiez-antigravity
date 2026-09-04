<?php

declare(strict_types=1);

namespace App\Modules\X199\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class InvoiceLine extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'invoice_lines';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'subtotal_cents' => 'integer',
    ];
}
