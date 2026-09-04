<?php

declare(strict_types=1);

namespace App\Modules\X164\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class EstimateVersion extends Model implements TenantScoped
{
    use BelongsToTenant;

    protected $table = 'estimate_versions';

    protected $guarded = [];

    protected $casts = [
        'version_number' => 'integer',
        'frozen_snapshot' => 'array',
    ];
}
