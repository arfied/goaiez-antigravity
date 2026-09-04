<?php

declare(strict_types=1);

namespace App\Modules\X199\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class OverflowCharge extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'overflow_charges';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];
}
