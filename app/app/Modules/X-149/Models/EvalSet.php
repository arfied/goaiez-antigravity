<?php

declare(strict_types=1);

namespace App\Modules\X149\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class EvalSet extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'eval_sets';

    protected $guarded = [];

    protected $casts = [
        'test_cases' => 'array',
    ];
}
