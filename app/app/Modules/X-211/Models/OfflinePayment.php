<?php

declare(strict_types=1);

namespace App\Modules\X211\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class OfflinePayment extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'offline_payments';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];
}
