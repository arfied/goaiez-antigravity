<?php

declare(strict_types=1);

namespace App\Modules\X151\Models;

use Illuminate\Database\Eloquent\Model;

class RobotsSignal extends Model
{
    protected $table = 'robots_signals';

    protected $guarded = [];

    protected $casts = [
        'crawl_delay' => 'integer',
        'disallowed_patterns' => 'array',
    ];
}
