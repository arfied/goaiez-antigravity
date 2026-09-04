<?php

declare(strict_types=1);

namespace App\Modules\X220\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class GoldenSet extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'golden_sets';

    protected $guarded = [];

    protected $casts = [
        'prompt_version' => 'integer',
        'test_cases' => 'array',
        'expected_outputs' => 'array',
        'score_threshold' => 'integer',
    ];
}
