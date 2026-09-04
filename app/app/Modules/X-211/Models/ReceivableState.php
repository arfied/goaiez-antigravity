<?php

declare(strict_types=1);

namespace App\Modules\X211\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivableState extends Model
{
    protected $table = 'receivable_states';

    protected $guarded = [];

    protected $casts = [
        'age_days' => 'integer',
        'late_fee_cents' => 'integer',
    ];
}
