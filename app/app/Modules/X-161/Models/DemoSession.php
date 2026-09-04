<?php

declare(strict_types=1);

namespace App\Modules\X161\Models;

use Illuminate\Database\Eloquent\Model;

class DemoSession extends Model
{
    protected $table = 'demo_sessions';

    protected $guarded = [];

    protected $casts = [
        'is_mock' => 'boolean',
    ];
}
