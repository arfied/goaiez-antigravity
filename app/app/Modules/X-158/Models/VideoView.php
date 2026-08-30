<?php

declare(strict_types=1);

namespace App\Modules\X158\Models;

use Illuminate\Database\Eloquent\Model;

class VideoView extends Model
{
    protected $table = 'video_views';

    protected $guarded = [];

    protected $casts = [
        'watch_duration_seconds' => 'integer',
        'watch_depth_percent' => 'float',
        'passed_50_percent' => 'boolean',
    ];
}
