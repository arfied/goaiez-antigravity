<?php

declare(strict_types=1);

namespace App\Modules\X163\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class CalloutFee extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'callout_fees';

    protected $guarded = [];

    protected $casts = [
        'callout_fee_cents' => 'integer',
        'deducted_if_proceeding' => 'boolean',
    ];
}
