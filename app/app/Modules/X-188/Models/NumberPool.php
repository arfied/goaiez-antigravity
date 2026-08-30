<?php

declare(strict_types=1);

namespace App\Modules\X188\Models;

use Illuminate\Database\Eloquent\Model;

class NumberPool extends Model
{
    protected $table = 'number_pool';

    protected $guarded = [];

    protected $casts = [
        'complaint_count' => 'integer',
    ];
}
