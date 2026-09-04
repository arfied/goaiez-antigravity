<?php

declare(strict_types=1);

namespace App\Modules\X201\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Dispute extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'disputes';

    protected $guarded = [];

    protected $casts = [
        'chargeback_amount_cents' => 'integer',
    ];
}
