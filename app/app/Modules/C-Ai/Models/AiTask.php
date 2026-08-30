<?php

declare(strict_types=1);

namespace App\Modules\CAi\Models;

use Illuminate\Database\Eloquent\Model;

class AiTask extends Model
{
    protected $table = 'ai_tasks';

    protected $guarded = [];

    protected $casts = [
        'max_ttft_ms' => 'integer',
        'cost_limit_cents' => 'integer',
    ];
}
