<?php

declare(strict_types=1);

namespace App\Modules\X141\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class Counterfactual extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'counterfactuals';

    protected $guarded = [];

    protected $casts = [
        'baseline_metric' => 'array',
        'simulated_metric' => 'array',
    ];
}
