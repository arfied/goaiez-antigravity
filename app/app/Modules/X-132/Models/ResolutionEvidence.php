<?php

declare(strict_types=1);

namespace App\Modules\X132\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ResolutionEvidence extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'resolution_evidence';

    protected $guarded = [];

    protected $casts = [
        'confidence_rate' => 'float',
    ];
}
