<?php

declare(strict_types=1);

namespace App\Modules\X195\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'feature_flags';

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'blast_radius_pct' => 'integer',
    ];
}
