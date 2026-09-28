<?php

declare(strict_types=1);

namespace App\Modules\X103\Models;

use Illuminate\Database\Eloquent\Model;

class SiteRecommendation extends Model
{
    protected $table = 'site_recommendations';

    protected $guarded = [];

    protected $casts = [
        'computed_at' => 'datetime',
    ];
}
