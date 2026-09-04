<?php

declare(strict_types=1);

namespace App\Modules\X158\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class VideoView extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'video_views';

    protected $guarded = [];

    protected $casts = [
        'watch_duration_seconds' => 'integer',
        'watch_depth_percent' => 'float',
        'passed_50_percent' => 'boolean',
    ];
}
