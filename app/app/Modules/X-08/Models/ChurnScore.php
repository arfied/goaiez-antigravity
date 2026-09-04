<?php

declare(strict_types=1);

namespace App\Modules\X08\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ChurnScore extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'churn_scores';

    protected $guarded = [];

    protected $casts = [
        'login_decay_days' => 'integer',
        'roi_open_rate_rising' => 'boolean',
    ];
}
