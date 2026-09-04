<?php

declare(strict_types=1);

namespace App\Modules\X211\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    protected $table = 'payment_plans';

    protected $guarded = [];

    protected $casts = [
        'installments_count' => 'integer',
        'installment_amount_cents' => 'integer',
    ];
}
