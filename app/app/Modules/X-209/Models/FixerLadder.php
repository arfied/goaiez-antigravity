<?php

declare(strict_types=1);

namespace App\Modules\X209\Models;

use Illuminate\Database\Eloquent\Model;

class FixerLadder extends Model
{
    protected $table = 'fixer_ladder';

    protected $guarded = [];

    protected $casts = [
        'current_level' => 'integer',
        'success_count' => 'integer',
    ];
}
