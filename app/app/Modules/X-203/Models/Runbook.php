<?php

declare(strict_types=1);

namespace App\Modules\X203\Models;

use Illuminate\Database\Eloquent\Model;

class Runbook extends Model
{
    protected $table = 'runbooks';

    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
    ];
}
