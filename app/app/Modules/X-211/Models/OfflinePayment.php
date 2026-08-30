<?php

declare(strict_types=1);

namespace App\Modules\X211\Models;

use Illuminate\Database\Eloquent\Model;

class OfflinePayment extends Model
{
    protected $table = 'offline_payments';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];
}
