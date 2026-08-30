<?php

declare(strict_types=1);

namespace App\Modules\X209\Models;

use Illuminate\Database\Eloquent\Model;

class FixerCommand extends Model
{
    protected $table = 'fixer_commands';

    protected $guarded = [];

    protected $casts = [
        'eta_minutes_delayed' => 'integer',
    ];
}
