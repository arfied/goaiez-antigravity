<?php

declare(strict_types=1);

namespace App\Modules\X195\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $table = 'feature_flags';

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'blast_radius_pct' => 'integer',
    ];
}
