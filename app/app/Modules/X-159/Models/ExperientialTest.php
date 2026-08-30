<?php

declare(strict_types=1);

namespace App\Modules\X159\Models;

use Illuminate\Database\Eloquent\Model;

class ExperientialTest extends Model
{
    protected $table = 'experiential_tests';

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
    ];
}
