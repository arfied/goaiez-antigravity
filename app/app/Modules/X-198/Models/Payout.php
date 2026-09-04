<?php

declare(strict_types=1);

namespace App\Modules\X198\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Payout extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'payouts';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'payout_date' => 'date',
    ];
}
