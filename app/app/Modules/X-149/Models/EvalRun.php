<?php

declare(strict_types=1);

namespace App\Modules\X149\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class EvalRun extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'eval_runs';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'sample_price_hallucinated' => 'boolean',
    ];
}
