<?php

declare(strict_types=1);

namespace App\Modules\X219\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $table = 'ai_models';

    protected $guarded = [];

    protected $casts = [
        'context_window' => 'integer',
        'cost_per_1k_input_cents' => 'integer',
        'cost_per_1k_output_cents' => 'integer',
        'capabilities' => 'array',
        'is_active' => 'boolean',
    ];
}
