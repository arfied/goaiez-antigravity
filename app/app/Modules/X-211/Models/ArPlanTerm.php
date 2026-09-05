<?php

declare(strict_types=1);

namespace App\Modules\X211\Models;

use Illuminate\Database\Eloquent\Model;

class ArPlanTerm extends Model
{
    protected $table = 'ar_plan_terms';

    protected $guarded = [];

    protected $attributes = [
        'max_installments' => 3,
        'max_term_days' => 90,
    ];

    protected $casts = [
        'max_installments' => 'integer',
        'max_term_days' => 'integer',
        'late_fee_percent' => 'integer',
        'late_fee_cap_cents' => 'integer',
    ];
}
