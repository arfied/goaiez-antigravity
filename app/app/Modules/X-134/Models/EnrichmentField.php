<?php

declare(strict_types=1);

namespace App\Modules\X134\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class EnrichmentField extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'enrichment_fields';

    protected $guarded = [];

    protected $casts = [
        'confidence_rate' => 'float',
        'is_usable' => 'boolean',
    ];
}
