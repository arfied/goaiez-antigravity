<?php

declare(strict_types=1);

namespace App\Modules\X149\Models;

use Illuminate\Database\Eloquent\Model;

class EvalRun extends Model
{
    protected $table = 'eval_runs';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'sample_price_hallucinated' => 'boolean',
    ];
}
