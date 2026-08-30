<?php

declare(strict_types=1);

namespace App\Modules\X149\Models;

use Illuminate\Database\Eloquent\Model;

class EvalSet extends Model
{
    protected $table = 'eval_sets';

    protected $guarded = [];

    protected $casts = [
        'test_cases' => 'array',
    ];
}
