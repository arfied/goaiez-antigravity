<?php

declare(strict_types=1);

namespace App\Modules\X150\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderRoster extends Model
{
    protected $table = 'provider_roster';

    protected $guarded = [];

    protected $casts = [
        'tier_level' => 'integer',
        'cost_per_lookup_cents' => 'integer',
        'is_active' => 'boolean',
    ];
}
