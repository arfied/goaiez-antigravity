<?php

declare(strict_types=1);

namespace App\Modules\X170\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'commissions';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'clawback_amount_cents' => 'integer',
    ];
}
